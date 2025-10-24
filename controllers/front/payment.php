<?php

use Symfony\Component\VarDumper\VarDumper;

class CazcoPayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $method = Tools::getValue('method') ?: 'boleto';
        \CazcoPayLogger::log('Acessou front/payment', 1, [
            'method' => $method,
            'cart_id' => (int) $this->context->cart->id,
        ]);

        // Valida método habilitado nas configurações
        $enabled = [
            'pix' => (bool) Configuration::get(\CazcoPayConfig::KEY_ENABLE_PIX),
            'boleto' => (bool) Configuration::get(\CazcoPayConfig::KEY_ENABLE_BOLETO),
            'card' => (bool) Configuration::get(\CazcoPayConfig::KEY_ENABLE_CARD),
        ];
        if (!isset($enabled[$method]) || !$enabled[$method]) {
            \CazcoPayLogger::log('Método não habilitado', 2, ['method' => $method]);
            Tools::redirect($this->context->link->getPageLink('order', true, null, ['step' => 3]));
            return;
        }

        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $totalAmount = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100); // em centavos

        $this->context->smarty->assign([
            'cazco_method' => pSQL($method),
            'module_dir' => _MODULE_DIR_ . $this->module->name . '/',
            'cart_total_cents' => $totalAmount,
            'currency_iso' => $currency->iso_code,
            'currency_sign' => $currency->sign,
        ]);

        switch ($method) {
            case 'pix':
                $this->handlePix($cart);
                $tpl = 'payment_pix.tpl';
                break;
            case 'card':
                $tpl = 'payment_card.tpl';
                break;
            case 'boleto':
            default:
                $tpl = 'payment_boleto.tpl';
                break;
        }

        $this->setTemplate('module:cazcopay/views/templates/front/' . $tpl);
    }

    /**
     * Gera transação Pix e prepara dados para o template.
     */
    protected function handlePix(Cart $cart)
    {
        try {
            $payload = $this->buildPixPayload($cart);
            \CazcoPayLogger::log('Criando transação PIX', 1, [
                'cart_id' => (int) $cart->id,
                'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            ]);

            $client = new \CazcoPayApiClient();
            $result = $client->createTransaction($payload);
            $body = $result['body'];

            if (empty($body['pix'])) {
                throw new Exception('Resposta da API sem dados PIX.');
            }

            $pix = $body['pix'];
            $transactionId = isset($body['id']) ? $body['id'] : null;

            \CazcoPayLogger::log('Transação PIX criada com sucesso', 1, [
                'cart_id' => (int) $cart->id,
                'transaction_id' => $transactionId,
                'amount' => (int) $payload['amount'],
            ]);

            $this->context->smarty->assign([
                'cazco_pix' => [
                    'transaction_id' => $transactionId,
                    'amount_cents' => (int) $payload['amount'],
                    'qrcode' => isset($pix['qrcode']) ? $pix['qrcode'] : '',
                    'url' => isset($pix['url']) ? $pix['url'] : '',
                    'expiration' => isset($pix['expirationDate']) ? $pix['expirationDate'] : null,
                    'raw' => $body,
                ],
            ]);
        } catch (\Exception $e) {
            \CazcoPayLogger::log('Erro ao criar transação PIX', 3, [
                'cart_id' => (int) $cart->id,
                'error' => $e->getMessage(),
            ]);

            $this->context->smarty->assign([
                'cazco_error' => $this->module->l('Não foi possível gerar o PIX no momento. Tente novamente em instantes ou selecione outro método de pagamento.'),
            ]);
        }
    }

    /**
     * Monta payload mínimo para criação da transação PIX.
     */
    protected function buildPixPayload(Cart $cart)
    {
        $amount = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100);
        $customer = new Customer((int) $cart->id_customer);

        $addressId = (int) $cart->id_address_invoice ?: (int) $cart->id_address_delivery;
        $address = $addressId ? new Address($addressId) : null;

        $document = $this->buildDocument($customer, $address);
        if (empty($document) && $address instanceof Address && !empty($address->vat_number)) {
            $document = $this->buildDocumentFromVat($address->vat_number);
        }

        $customerData = array_filter([
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
            'email' => $customer->email,
            'phone' => $this->sanitizePhone($address),
            'document' => $document,
        ]);

        $items = [];
        foreach ($cart->getProducts(true) as $product) {
            $items[] = [
                'title' => $product['name'],
                'quantity' => (int) $product['cart_quantity'],
                'unitPrice' => (int) round($product['price_wt'] * 100),
                'tangible' => !(bool) $product['is_virtual'],
            ];
        }

        $metadata = [
            'cart_id' => (int) $cart->id,
            'module_version' => $this->module->version,
            'shop_id' => (int) $this->context->shop->id,
        ];

        $payload = [
            'amount' => $amount,
            'paymentMethod' => 'pix',
            'customer' => $customerData,
            'externalRef' => (string) $cart->id,
            'metadata' => $metadata,
        ];

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        return $payload;
    }

    protected function sanitizePhone($address)
    {
        if (!$address instanceof Address) {
            return null;
        }

        $phone = $address->phone_mobile ?: $address->phone;
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        return $digits ?: null;
    }

    protected function buildDocument(Customer $customer, $address)
    {
        $dni = '';
        if (!empty($customer->dni)) {
            $dni = $customer->dni;
        } elseif ($address instanceof Address && !empty($address->dni)) {
            $dni = $address->dni;
        }

        $digits = preg_replace('/\D+/', '', (string) $dni);
        if (strlen($digits) === 11) {
            return [
                'number' => $digits,
                'type' => 'cpf',
            ];
        }
        if (strlen($digits) === 14) {
            return [
                'number' => $digits,
                'type' => 'cnpj',
            ];
        }

        return null;
    }

    protected function buildDocumentFromVat($vatNumber)
    {
        $digits = preg_replace('/\D+/', '', (string) $vatNumber);
        if (strlen($digits) === 11) {
            return [
                'number' => $digits,
                'type' => 'cpf',
            ];
        }
        if (strlen($digits) === 14) {
            return [
                'number' => $digits,
                'type' => 'cnpj',
            ];
        }

        return null;
    }
}
