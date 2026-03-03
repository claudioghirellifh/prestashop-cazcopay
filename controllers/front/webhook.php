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

    /**
     * @var string
     */
    private $providedToken = '';

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
        $this->providedToken = $this->resolveProvidedToken();
        if (empty($this->secret)) {
            $this->persistPostbackLog(500, 'webhook_not_configured', [
                'token_valid' => 0,
                'error_message' => 'Webhook secret ausente.',
            ]);
            CazcoPayLogger::log('Webhook não configurado: segredo ausente.', 3, [
                'ip' => Tools::getRemoteAddr(),
            ]);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'webhook_not_configured'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

        CazcoPayLogger::log('Webhook recebido.', 1, [
            'ip' => Tools::getRemoteAddr(),
            'method' => $method,
            'has_token' => $this->providedToken !== '',
        ]);

        if ($this->providedToken !== $this->secret) {
            $this->persistPostbackLog(404, 'invalid_token', [
                'token_valid' => 0,
                'error_message' => 'Token do webhook inválido.',
            ]);
            CazcoPayLogger::log('Token do webhook inválido.', 3, [
                'ip' => Tools::getRemoteAddr(),
            ]);
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method !== 'POST') {
            $this->persistPostbackLog(405, 'invalid_method', [
                'token_valid' => 1,
                'error_message' => 'Método HTTP inválido: ' . $method,
            ]);
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
        $payload = [];
        $status = '';
        $transactionId = '';
        $objectId = '';
        $orderId = 0;

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

            if (trim($this->rawPayload) === '') {
                throw new InvalidArgumentException('Payload vazio.');
            }

            $payload = json_decode($this->rawPayload, true);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Payload inválido.');
            }

            $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
            $status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
            $transactionId = isset($data['id']) ? (string) $data['id'] : '';
            $objectId = isset($payload['objectId']) ? (string) $payload['objectId'] : '';
            $order = $this->resolveOrderFromPayload($data, $transactionId, $objectId);

            if ($order instanceof Order && Validate::isLoadedObject($order)) {
                $orderId = (int) $order->id;
                $this->syncOrderStatus($order, $status);
                $this->saveCazcoPayPayload($order, $transactionId, $payload, $data);
            } else {
                CazcoPayLogger::log('Webhook sem pedido associado.', 2, [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                ]);
            }

            $this->persistPostbackLog(200, 'ok', [
                'token_valid' => 1,
                'payment_status' => $status,
                'transaction_id' => $transactionId,
                'object_id' => $objectId,
                'id_order' => $orderId,
                'payload' => $payload,
            ]);

            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\InvalidArgumentException $e) {
            $this->persistPostbackLog(400, 'invalid_payload', [
                'token_valid' => $this->providedToken !== '' && $this->providedToken === $this->secret ? 1 : 0,
                'payment_status' => $status,
                'transaction_id' => $transactionId,
                'object_id' => $objectId,
                'id_order' => $orderId,
                'error_message' => $e->getMessage(),
                'payload' => $this->rawPayload,
            ]);
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            $this->persistPostbackLog(500, 'internal_error', [
                'token_valid' => $this->providedToken !== '' && $this->providedToken === $this->secret ? 1 : 0,
                'payment_status' => $status,
                'transaction_id' => $transactionId,
                'object_id' => $objectId,
                'id_order' => $orderId,
                'error_message' => $e->getMessage(),
                'payload' => !empty($payload) ? $payload : $this->rawPayload,
            ]);
            http_response_code(500);
            header('Content-Type: application/json');
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

        $existing = method_exists($this->module, 'getPixData')
            ? $this->module->getPixData((int) $order->id)
            : null;
        $paymentMethod = isset($data['paymentMethod']) ? strtolower(trim((string) $data['paymentMethod'])) : '';

        $pixData = isset($data['pix']) && is_array($data['pix']) ? $data['pix'] : [];
        $boletoData = isset($data['boleto']) && is_array($data['boleto']) ? $data['boleto'] : [];

        $qrcode = '';
        $url = isset($data['secureUrl']) ? (string) $data['secureUrl'] : '';
        $expiration = null;

        if (!empty($pixData)) {
            $qrcode = (string) ($pixData['qrcode'] ?? '');
            if (!empty($pixData['url'])) {
                $url = (string) $pixData['url'];
            }
            if (!empty($pixData['expirationDate'])) {
                $expiration = (string) $pixData['expirationDate'];
            }
            if ($paymentMethod === '') {
                $paymentMethod = 'pix';
            }
        }

        if (!empty($boletoData)) {
            $qrcode = (string) ($boletoData['digitableLine'] ?? $boletoData['barcode'] ?? $qrcode);
            if (!empty($boletoData['url'])) {
                $url = (string) $boletoData['url'];
            }
            if (!empty($boletoData['expirationDate'])) {
                $expiration = (string) $boletoData['expirationDate'];
            }
            if ($paymentMethod === '') {
                $paymentMethod = 'boleto';
            }
        }

        if ($qrcode === '' && !empty($existing['pix_qrcode'])) {
            $qrcode = (string) $existing['pix_qrcode'];
        }
        if ($url === '' && !empty($existing['pix_url'])) {
            $url = (string) $existing['pix_url'];
        }
        if ($expiration === null && !empty($existing['pix_expiration'])) {
            $expiration = (string) $existing['pix_expiration'];
        }
        if ($paymentMethod === '' && !empty($existing['payment_method'])) {
            $paymentMethod = (string) $existing['payment_method'];
        }

        $paidAt = isset($data['paidAt']) ? (string) $data['paidAt'] : null;
        if ($expiration === null && $paidAt) {
            $expiration = $paidAt;
        }
        $this->module->savePixData((int) $order->id, [
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'qrcode' => $qrcode,
            'url' => $url,
            'expiration' => $expiration,
            'amount' => isset($data['amount']) ? (int) $data['amount'] : null,
            'payload' => $payload,
        ]);
    }

    private function persistPostbackLog($httpCode, $result, array $context = [])
    {
        if (!$this->module || !method_exists($this->module, 'saveWebhookLog')) {
            return;
        }

        $this->module->saveWebhookLog([
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
            'ip_address' => Tools::getRemoteAddr(),
            'token_valid' => isset($context['token_valid']) ? (int) $context['token_valid'] : 0,
            'http_code' => (int) $httpCode,
            'result' => (string) $result,
            'payment_status' => isset($context['payment_status']) ? (string) $context['payment_status'] : '',
            'transaction_id' => isset($context['transaction_id']) ? (string) $context['transaction_id'] : '',
            'object_id' => isset($context['object_id']) ? (string) $context['object_id'] : '',
            'id_order' => isset($context['id_order']) ? (int) $context['id_order'] : 0,
            'error_message' => isset($context['error_message']) ? (string) $context['error_message'] : '',
            'payload' => $context['payload'] ?? '',
        ]);
    }

    private function resolveProvidedToken()
    {
        $token = trim((string) Tools::getValue('token', ''));
        if ($token !== '') {
            return $token;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && preg_match('#/cazcopay/webhook/([_A-Za-z0-9-]{8,})(?:[/?#]|$)#', $uri, $matches)) {
            return (string) $matches[1];
        }

        return '';
    }
}
