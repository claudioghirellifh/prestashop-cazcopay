<?php
/**
 * Webhook endpoint for Cazco Pay.
 *
 * @since 0.1.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CazcoPayWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $ssl = false;

    /**
     * @var bool
     */
    public $ajax = true;

    /**
     * @var bool
     */
    public $display_header = false;

    /**
     * @var bool
     */
    public $display_header_javascript = false;

    /**
     * @var bool
     */
    public $display_footer = false;

    /**
     * @var bool
     */
    public $content_only = true;

    /**
     * @var string
     */
    private $secret = '';

    /**
     * @var string
     */
    private $rawPayload = '';

    public function __construct()
    {
        parent::__construct();
        $this->ssl = (bool) Configuration::get('PS_SSL_ENABLED');
    }

    /**
     * Webhook does not require authentication.
     *
     * @var bool
     */
    public $auth = false;

    /**
     * Allow guests to trigger the controller.
     *
     * @var bool
     */
    public $guestAllowed = true;

    /**
     * Disable default template rendering.
     *
     * @var string
     */
    protected $template = '';

    /**
     * Initialize controller and ensure the request method is POST.
     */
    public function init()
    {
        // No template rendering or CSRF checks required for webhooks.
        parent::init();

        $this->secret = CazcoPayConfig::getWebhookSecret();
        if (empty($this->secret)) {
            CazcoPayLogger::log('Webhook não configurado: segredo ausente.', 3, [
                'ip' => Tools::getRemoteAddr(),
            ]);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'webhook_not_configured'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $providedToken = (string) Tools::getValue('token', '');
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

        CazcoPayLogger::log('Webhook recebido.', 1, [
            'ip' => Tools::getRemoteAddr(),
            'method' => $method,
            'has_token' => $providedToken !== '',
        ]);

        if ($providedToken !== $this->secret) {
            CazcoPayLogger::log('Token do webhook inválido.', 3, [
                'ip' => Tools::getRemoteAddr(),
            ]);
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method !== 'POST') {
            CazcoPayLogger::log('Webhook com método inválido.', 2, [
                'ip' => Tools::getRemoteAddr(),
                'method' => $method,
            ]);
            header('Allow: POST');
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function initContent()
    {
        // Skip Template rendering.
    }

    /**
     * Process webhook payload.
     */
    public function postProcess()
    {
        try {
            $this->rawPayload = (string) file_get_contents('php://input');
            $preview = $this->rawPayload;
            if (strlen($preview) > 1000) {
                $preview = substr($preview, 0, 1000) . '...';
            }

            CazcoPayLogger::log('Webhook payload recebido.', 1, [
                'ip' => Tools::getRemoteAddr(),
                'content_type' => isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : null,
                'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null,
                'payload_preview' => $preview,
            ]);

            $payload = json_decode($this->rawPayload, true);
            if (!is_array($payload)) {
                throw new Exception('Payload inválido.');
            }

            $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
            $status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
            $transactionId = isset($data['id']) ? (string) $data['id'] : '';
            $objectId = isset($payload['objectId']) ? (string) $payload['objectId'] : '';
            $order = $this->resolveOrderFromPayload($data, $transactionId, $objectId);

            if ($order instanceof Order && Validate::isLoadedObject($order)) {
                $this->syncOrderStatus($order, $status);
                $this->saveCazcoPayPayload($order, $transactionId, $payload, $data);
            } else {
                CazcoPayLogger::log('Webhook sem pedido associado.', 2, [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                ]);
            }

            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'internal_error'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function resolveOrderFromPayload(array $data, $transactionId, $objectId = '')
    {
        $order = null;

        if ($transactionId !== '') {
            $sql = new DbQuery();
            $sql->select('id_order');
            $sql->from('cazcopay_order');
            $sql->where('transaction_id = "' . pSQL($transactionId) . '"');
            $idOrder = (int) Db::getInstance()->getValue($sql);
            if ($idOrder > 0) {
                $order = new Order($idOrder);
            }
        }

        if ((!$order instanceof Order || !Validate::isLoadedObject($order)) && $objectId !== '') {
            $objectIdInt = (int) $objectId;
            if ($objectIdInt > 0) {
                $order = new Order($objectIdInt);
            }
        }

        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            $reference = '';
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $reference = isset($data['metadata']['order_reference']) ? (string) $data['metadata']['order_reference'] : '';
            }
            if ($reference !== '') {
                $collection = Order::getByReference($reference);
                if (is_array($collection) && !empty($collection)) {
                    $order = new Order((int) $collection[0]->id);
                }
            }
        }

        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            $cartId = 0;
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $cartId = (int) ($data['metadata']['id_cart'] ?? 0);
            }
            if ($cartId > 0) {
                $sql = new DbQuery();
                $sql->select('id_order');
                $sql->from('orders');
                $sql->where('id_cart = ' . (int) $cartId);
                $idOrder = (int) Db::getInstance()->getValue($sql);
                if ($idOrder > 0) {
                    $order = new Order($idOrder);
                }
            }
        }

        if ($order instanceof Order && Validate::isLoadedObject($order)) {
            CazcoPayLogger::log('Webhook pedido associado.', 1, [
                'order_id' => (int) $order->id,
                'transaction_id' => $transactionId,
            ]);
        }

        return $order;
    }

    private function syncOrderStatus(Order $order, $status)
    {
        if ($status === '') {
            return;
        }

        if ($status === 'paid') {
            $paidState = (int) Configuration::get('PS_OS_PAYMENT');
            if ($paidState > 0 && (int) $order->current_state !== $paidState) {
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->changeIdOrderState($paidState, (int) $order->id);
                $history->addWithemail(true);
            }
        }
    }

    private function saveCazcoPayPayload(Order $order, $transactionId, array $payload, array $data)
    {
        if (!$this->module || !method_exists($this->module, 'savePixData')) {
            return;
        }

        $paidAt = isset($data['paidAt']) ? (string) $data['paidAt'] : null;
        $this->module->savePixData((int) $order->id, [
            'payment_method' => isset($data['paymentMethod']) ? (string) $data['paymentMethod'] : '',
            'transaction_id' => $transactionId,
            'qrcode' => '',
            'url' => isset($data['secureUrl']) ? (string) $data['secureUrl'] : '',
            'expiration' => $paidAt,
            'amount' => isset($data['amount']) ? (int) $data['amount'] : null,
            'payload' => $payload,
        ]);
    }
}
