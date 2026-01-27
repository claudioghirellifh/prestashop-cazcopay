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

            $customer = new Customer((int) $cart->id_customer);
            $currency = $this->context->currency;
            $orderState = $this->module->ensurePixOrderState();
            if (!$orderState) {
                throw new Exception('Estado de pedido para PIX não configurado.');
            }

            $this->module->validateOrder(
                (int) $cart->id,
                $orderState,
                (float) $payload['amount'] / 100,
                $this->module->l('Cazco Pay - PIX', 'payment'),
                null,
                ['transaction_id' => $transactionId],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $idOrder = (int) $this->module->currentOrder;

            $this->module->savePixData($idOrder, [
                'payment_method' => 'pix',
                'transaction_id' => $transactionId,
                'qrcode' => isset($pix['qrcode']) ? $pix['qrcode'] : '',
                'url' => isset($pix['url']) ? $pix['url'] : '',
                'expiration' => isset($pix['expirationDate']) ? $pix['expirationDate'] : null,
                'amount' => (int) $payload['amount'],
                'payload' => $body,
            ]);

            $redirectUrl = $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                [
                    'id_cart' => (int) $cart->id,
                    'id_module' => (int) $this->module->id,
                    'id_order' => $idOrder,
                    'key' => $customer->secure_key,
                ]
            );

            Tools::redirect($redirectUrl);
            exit;
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

    protected function buildDocument(Customer $customer, $address, $source = null)
    {
        if (!$source) {
            $source = CazcoPayConfig::getDocumentSource();
        }

        if (strpos($source, 'cbcz_customer:') === 0) {
            $fieldKey = substr($source, strlen('cbcz_customer:'));
            $value = $this->getCadastroBrasilCustomerFieldValue((int) $customer->id, $fieldKey);
            $document = $this->buildDocumentFromCustomValue($value);
            if ($document) {
                return $document;
            }
            $source = 'auto';
        } elseif (strpos($source, 'cbcz_address:') === 0) {
            $fieldKey = substr($source, strlen('cbcz_address:'));
            $addressId = $address instanceof Address ? (int) $address->id : 0;
            $value = $this->getCadastroBrasilAddressFieldValue($addressId, $fieldKey);
            $document = $this->buildDocumentFromCustomValue($value);
            if ($document) {
                return $document;
            }
            $source = 'auto';
        } elseif ($source === 'cbcz_customer') {
            $fieldKey = CazcoPayConfig::getDocumentCustomerFieldKey();
            $value = $this->getCadastroBrasilCustomerFieldValue((int) $customer->id, $fieldKey);
            $document = $this->buildDocumentFromCustomValue($value);
            if ($document) {
                return $document;
            }
            $source = 'auto';
        } elseif ($source === 'cbcz_address') {
            $fieldKey = CazcoPayConfig::getDocumentAddressFieldKey();
            $addressId = $address instanceof Address ? (int) $address->id : 0;
            $value = $this->getCadastroBrasilAddressFieldValue($addressId, $fieldKey);
            $document = $this->buildDocumentFromCustomValue($value);
            if ($document) {
                return $document;
            }
            $source = 'auto';
        }

        $dni = '';
        if ($source === 'customer_dni') {
            $dni = $customer->dni;
        } elseif ($source === 'address_dni') {
            $dni = $address instanceof Address ? $address->dni : '';
        } elseif ($source === 'address_vat') {
            return $this->buildDocumentFromVat(
                $address instanceof Address ? $address->vat_number : ''
            );
        } else {
            if (!empty($customer->dni)) {
                $dni = $customer->dni;
            } elseif ($address instanceof Address && !empty($address->dni)) {
                $dni = $address->dni;
            } elseif ($address instanceof Address && !empty($address->vat_number)) {
                return $this->buildDocumentFromVat($address->vat_number);
            }
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

    protected function buildDocumentFromCustomValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
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

    protected function getCadastroBrasilCustomerFieldValue($idCustomer, $fieldKey)
    {
        if ($idCustomer <= 0 || $fieldKey === '') {
            return null;
        }
        if (!$this->hasCadastroBrasilFieldTable()) {
            return null;
        }
        $sql = new DbQuery();
        $sql->select('v.value');
        $sql->from('cadastrobrasilcazco_customer_value', 'v');
        $sql->leftJoin('cadastrobrasilcazco_field', 'f', 'v.id_cadastrobrasilcazco_field = f.id_cadastrobrasilcazco_field');
        $sql->where('v.id_customer=' . (int) $idCustomer);
        $sql->where('v.id_shop=' . (int) $this->context->shop->id);
        $sql->where('f.field_key="' . pSQL($fieldKey) . '"');

        return Db::getInstance()->getValue($sql);
    }

    protected function getCadastroBrasilAddressFieldValue($idAddress, $fieldKey)
    {
        if ($idAddress <= 0 || $fieldKey === '') {
            return null;
        }
        if (!$this->hasCadastroBrasilFieldTable()) {
            return null;
        }
        $sql = new DbQuery();
        $sql->select('v.value');
        $sql->from('cadastrobrasilcazco_address_value', 'v');
        $sql->leftJoin('cadastrobrasilcazco_field', 'f', 'v.id_cadastrobrasilcazco_field = f.id_cadastrobrasilcazco_field');
        $sql->where('v.id_address=' . (int) $idAddress);
        $sql->where('v.id_shop=' . (int) $this->context->shop->id);
        $sql->where('f.field_key="' . pSQL($fieldKey) . '"');

        return Db::getInstance()->getValue($sql);
    }

    protected function hasCadastroBrasilFieldTable()
    {
        $table = _DB_PREFIX_ . 'cadastrobrasilcazco_field';
        $exists = Db::getInstance()->getValue(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = "' . pSQL($table) . '"'
        );
        return (bool) $exists;
    }
}
