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

            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate');

            echo json_encode(['message' => 'oi'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'internal_error'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
