<?php

class CazcoPayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $method = strtolower(trim((string) Tools::getValue('method', 'boleto')));
        if ($method === '') {
            $method = 'boleto';
        }
        \CazcoPayLogger::log('Acessou front/payment', 1, [
            'method' => $method,
            'cart_id' => (int) $this->context->cart->id,
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '',
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
                $this->handleCard($cart);
                $tpl = 'payment_card.tpl';
                break;
            case 'boleto':
                $this->handleBoleto($cart);
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
     * Gera transação Boleto e prepara dados para o retorno do pedido.
     */
    protected function handleBoleto(Cart $cart)
    {
        try {
            $payload = $this->buildBoletoPayload($cart);
            \CazcoPayLogger::log('Criando transação Boleto', 1, [
                'cart_id' => (int) $cart->id,
                'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            ]);

            $client = new \CazcoPayApiClient();
            $result = $client->createTransaction($payload);
            $body = $result['body'];
            $boleto = isset($body['boleto']) && is_array($body['boleto']) ? $body['boleto'] : [];
            if (empty($boleto)) {
                throw new Exception('Resposta da API sem dados de boleto.');
            }

            $transactionId = isset($body['id']) ? (string) $body['id'] : '';

            \CazcoPayLogger::log('Transação Boleto criada com sucesso', 1, [
                'cart_id' => (int) $cart->id,
                'transaction_id' => $transactionId,
                'amount' => (int) $payload['amount'],
            ]);

            $customer = new Customer((int) $cart->id_customer);
            $currency = $this->context->currency;
            $orderState = $this->module->ensureBoletoOrderState();
            if (!$orderState) {
                throw new Exception('Estado de pedido para Boleto não configurado.');
            }

            $this->module->validateOrder(
                (int) $cart->id,
                $orderState,
                (float) $payload['amount'] / 100,
                $this->module->l('Cazco Pay - Boleto', 'payment'),
                null,
                ['transaction_id' => $transactionId],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $idOrder = (int) $this->module->currentOrder;

            $this->module->savePixData($idOrder, [
                'payment_method' => 'boleto',
                'transaction_id' => $transactionId,
                'qrcode' => $this->extractBoletoDigitableLine($boleto),
                'url' => $this->extractBoletoUrl($boleto, $body),
                'expiration' => isset($boleto['expirationDate']) ? $boleto['expirationDate'] : null,
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
            \CazcoPayLogger::log('Erro ao criar transação Boleto', 3, [
                'cart_id' => (int) $cart->id,
                'error' => $e->getMessage(),
            ]);

            $this->context->smarty->assign([
                'cazco_error' => $this->module->l('Não foi possível gerar o boleto no momento. Tente novamente em instantes ou selecione outro método de pagamento.'),
            ]);
        }
    }

    /**
     * Gera transação de Cartão e finaliza o pedido.
     */
    protected function handleCard(Cart $cart)
    {
        try {
            $payload = $this->buildCardPayload($cart);
            if ($payload === null) {
                $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
                if ($requestMethod === 'POST') {
                    $postKeys = [];
                    if (is_array($_POST)) {
                        $postKeys = array_slice(array_keys($_POST), 0, 50);
                    }
                    \CazcoPayLogger::log('POST de cartão sem campos esperados', 2, [
                        'cart_id' => (int) $cart->id,
                        'post_keys' => $postKeys,
                    ]);
                    throw new Exception('Dados do cartão não recebidos na submissão do checkout.');
                }
                return;
            }

            \CazcoPayLogger::log('Criando transação Cartão', 1, [
                'cart_id' => (int) $cart->id,
                'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
                'installments' => isset($payload['installments']) ? (int) $payload['installments'] : 1,
            ]);

            $client = new \CazcoPayApiClient();
            $result = $client->createTransaction($payload);
            $body = $result['body'];
            $transactionId = isset($body['id']) ? (string) $body['id'] : '';
            if ($transactionId === '') {
                throw new Exception('Resposta da API sem identificador da transação.');
            }

            $status = isset($body['status']) ? strtolower(trim((string) $body['status'])) : '';

            \CazcoPayLogger::log('Transação Cartão criada com sucesso', 1, [
                'cart_id' => (int) $cart->id,
                'transaction_id' => $transactionId,
                'amount' => (int) $payload['amount'],
                'status' => $status,
            ]);

            $customer = new Customer((int) $cart->id_customer);
            $currency = $this->context->currency;

            $orderState = 0;
            if ($status === 'paid') {
                $orderState = (int) Configuration::get('PS_OS_PAYMENT');
            }
            if ($orderState <= 0) {
                $orderState = $this->module->ensureCardOrderState();
            }
            if ($orderState <= 0) {
                throw new Exception('Estado de pedido para Cartão não configurado.');
            }

            $this->module->validateOrder(
                (int) $cart->id,
                $orderState,
                (float) $payload['amount'] / 100,
                $this->module->l('Cazco Pay - Cartão', 'payment'),
                null,
                ['transaction_id' => $transactionId],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $idOrder = (int) $this->module->currentOrder;

            $this->module->savePixData($idOrder, [
                'payment_method' => 'card',
                'transaction_id' => $transactionId,
                'qrcode' => '',
                'url' => '',
                'expiration' => isset($body['createdAt']) ? $body['createdAt'] : null,
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
            \CazcoPayLogger::log('Erro ao criar transação Cartão', 3, [
                'cart_id' => (int) $cart->id,
                'error' => $e->getMessage(),
            ]);

            $this->context->smarty->assign([
                'cazco_error' => $this->module->l('Não foi possível processar o cartão no momento. Revise os dados e tente novamente.'),
                'cazco_error_detail' => $e->getMessage(),
            ]);
        }
    }

    protected function extractBoletoDigitableLine(array $boleto)
    {
        $keys = ['digitableLine', 'line', 'barcode', 'barCode'];
        foreach ($keys as $key) {
            if (!empty($boleto[$key])) {
                return (string) $boleto[$key];
            }
        }

        return '';
    }

    protected function extractBoletoUrl(array $boleto, array $body)
    {
        $keys = ['url', 'secureUrl', 'link'];
        foreach ($keys as $key) {
            if (!empty($boleto[$key])) {
                return (string) $boleto[$key];
            }
        }

        foreach ($keys as $key) {
            if (!empty($body[$key])) {
                return (string) $body[$key];
            }
        }

        return '';
    }

    /**
     * Monta payload mínimo para criação da transação PIX.
     */
    protected function buildPixPayload(Cart $cart)
    {
        return $this->buildTransactionPayload($cart, 'pix');
    }

    /**
     * Monta payload mínimo para criação da transação de boleto.
     */
    protected function buildBoletoPayload(Cart $cart)
    {
        return $this->buildTransactionPayload($cart, 'boleto');
    }

    /**
     * Monta payload para criação da transação de cartão.
     */
    protected function buildCardPayload(Cart $cart)
    {
        $number = preg_replace('/\D+/', '', (string) Tools::getValue('cc-number', ''));
        $holderName = trim((string) Tools::getValue('cc-holder', ''));
        $cpfDocument = preg_replace('/\D+/', '', (string) Tools::getValue('cc-document-cpf', ''));
        $expMonth = (int) Tools::getValue('cc-exp-month', 0);
        $expYear = (int) Tools::getValue('cc-exp-year', 0);
        $cvv = preg_replace('/\D+/', '', (string) Tools::getValue('cc-cvv', ''));
        $installments = (int) Tools::getValue('cc-installments', 1);

        if ($number === '' && $holderName === '' && $cpfDocument === '' && $expMonth === 0 && $expYear === 0 && $cvv === '') {
            return null;
        }

        if (strlen($number) < 13 || strlen($number) > 19) {
            throw new Exception('Número do cartão inválido.');
        }
        if ($holderName === '') {
            throw new Exception('Nome impresso do cartão é obrigatório.');
        }
        if ($cpfDocument === '') {
            throw new Exception('CPF do titular do cartão é obrigatório.');
        }
        if (!$this->isValidCpf($cpfDocument)) {
            throw new Exception('CPF do titular do cartão inválido.');
        }
        if ($expMonth < 1 || $expMonth > 12) {
            throw new Exception('Mês de validade do cartão inválido.');
        }
        if ($expYear < (int) date('Y') || $expYear > ((int) date('Y') + 20)) {
            throw new Exception('Ano de validade do cartão inválido.');
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw new Exception('CVV do cartão inválido.');
        }

        $maxInstallments = \CazcoPayConfig::getInstallmentsMax();
        $installments = max(1, min($maxInstallments, $installments));

        $payload = $this->buildTransactionPayload($cart, 'credit_card');
        $payload['installments'] = $installments;
        $payload['card'] = [
            'number' => $number,
            'holderName' => $holderName,
            'expirationMonth' => $expMonth,
            'expirationYear' => $expYear,
            'cvv' => $cvv,
        ];
        if (!isset($payload['customer']) || !is_array($payload['customer'])) {
            $payload['customer'] = [];
        }
        $payload['customer']['document'] = [
            'number' => $cpfDocument,
            'type' => 'cpf',
        ];

        return $payload;
    }

    protected function isValidCpf($cpf)
    {
        $digits = preg_replace('/\D+/', '', (string) $cpf);
        if (strlen($digits) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += ((int) $digits[$i]) * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $digits[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Monta payload mínimo para criação da transação.
     */
    protected function buildTransactionPayload(Cart $cart, $paymentMethod)
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
            'id_cart' => (int) $cart->id,
            'cart_id' => (int) $cart->id,
            'module_version' => $this->module->version,
            'shop_id' => (int) $this->context->shop->id,
        ];

        $payload = [
            'amount' => $amount,
            'paymentMethod' => (string) $paymentMethod,
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
        $cpfKey = CazcoPayConfig::getDocumentCpfFieldKey();
        $cnpjKey = CazcoPayConfig::getDocumentCnpjFieldKey();
        if ($cpfKey !== '') {
            $doc = $this->buildDocumentFromCustomValue(
                $this->resolveMappedFieldValue($customer, $address, $cpfKey)
            );
            if ($doc) {
                return $doc;
            }
        }

        if ($cnpjKey !== '') {
            $doc = $this->buildDocumentFromCustomValue(
                $this->resolveMappedFieldValue($customer, $address, $cnpjKey)
            );
            if ($doc) {
                return $doc;
            }
        }

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

    protected function resolveMappedFieldValue(Customer $customer, $address, $mapping)
    {
        $mapping = (string) $mapping;
        if ($mapping === '') {
            return null;
        }

        if (strpos($mapping, 'ps_customer:') === 0) {
            $key = substr($mapping, strlen('ps_customer:'));
            return $this->resolveCustomerFieldValue($customer, $key);
        }
        if (strpos($mapping, 'ps_address:') === 0) {
            $key = substr($mapping, strlen('ps_address:'));
            return $this->resolveAddressFieldValue($address, $key);
        }
        if (strpos($mapping, 'cbcz_customer:') === 0) {
            $key = substr($mapping, strlen('cbcz_customer:'));
            return $this->getCadastroBrasilCustomerFieldValue((int) $customer->id, $key);
        }
        if (strpos($mapping, 'cbcz_address:') === 0) {
            $key = substr($mapping, strlen('cbcz_address:'));
            $addressId = $address instanceof Address ? (int) $address->id : 0;
            return $this->getCadastroBrasilAddressFieldValue($addressId, $key);
        }

        return null;
    }

    protected function resolveCustomerFieldValue(Customer $customer, $key)
    {
        $key = (string) $key;
        if ($key === '') {
            return null;
        }

        switch ($key) {
            case 'dni':
                return isset($customer->dni) ? (string) $customer->dni : null;
        }

        return null;
    }

    protected function resolveAddressFieldValue($address, $key)
    {
        if (!$address instanceof Address) {
            return null;
        }

        $key = (string) $key;
        if ($key === '') {
            return null;
        }

        switch ($key) {
            case 'number':
                return isset($address->number) ? (string) $address->number : null;
            case 'dni':
                return isset($address->dni) ? (string) $address->dni : null;
            case 'vat_number':
                return isset($address->vat_number) ? (string) $address->vat_number : null;
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
