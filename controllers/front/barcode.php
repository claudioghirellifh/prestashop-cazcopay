<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class CazcoPayBarcodeModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;
    public $display_header = false;
    public $display_header_javascript = false;
    public $display_footer = false;
    public $content_only = true;
    public $auth = false;
    public $guestAllowed = true;
    protected $template = '';

    public function initContent()
    {
        // no-op
    }

    public function postProcess()
    {
        $code = preg_replace('/\D+/', '', (string) Tools::getValue('code'));
        if ($code === '') {
            $this->sendError(400);
            return;
        }

        $type = 'I25';
        if (strlen($code) === 47) {
            $code = $this->convertDigitableLineToBarcode($code);
        }
        if (strlen($code) !== 44) {
            // Fallback visual para codigos curtos de sandbox/teste.
            $type = 'C128';
            if (strlen($code) < 4) {
                $this->sendError(422);
                return;
            }
        }

        $width = (int) Tools::getValue('w', 2);
        $height = (int) Tools::getValue('h', 72);
        $width = max(1, min(4, $width));
        $height = max(40, min(120, $height));

        $tcpdfBarcodePath = _PS_ROOT_DIR_ . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_1d.php';
        if (!is_file($tcpdfBarcodePath)) {
            $this->sendError(500);
            return;
        }
        require_once $tcpdfBarcodePath;

        try {
            $barcode = new TCPDFBarcode($code, $type);
            $png = $barcode->getBarcodePngData($width, $height, [0, 0, 0]);
            if (!is_string($png) || $png === '') {
                $this->sendError(500);
                return;
            }

            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            header('Content-Length: ' . strlen($png));
            echo $png;
            exit;
        } catch (Throwable $e) {
            CazcoPayLogger::log('Falha ao gerar imagem de codigo de barras', 3, [
                'error' => $e->getMessage(),
                'code_length' => strlen($code),
            ]);
            $this->sendError(500);
        }
    }

    private function sendError($statusCode)
    {
        http_response_code((int) $statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'barcode_error';
        exit;
    }

    private function convertDigitableLineToBarcode($digitableLine)
    {
        $line = preg_replace('/\D+/', '', (string) $digitableLine);
        if (strlen($line) !== 47) {
            return '';
        }

        $campo1 = substr($line, 0, 10);
        $campo2 = substr($line, 10, 11);
        $campo3 = substr($line, 21, 11);
        $campo4 = substr($line, 32, 1);
        $campo5 = substr($line, 33, 14);

        if ($campo1 === '' || $campo2 === '' || $campo3 === '' || $campo4 === '' || $campo5 === '') {
            return '';
        }

        $campo1SemDv = substr($campo1, 0, 9);
        $campo2SemDv = substr($campo2, 0, 10);
        $campo3SemDv = substr($campo3, 0, 10);

        return substr($campo1SemDv, 0, 4)
            . $campo4
            . $campo5
            . substr($campo1SemDv, 4, 5)
            . $campo2SemDv
            . $campo3SemDv;
    }
}
