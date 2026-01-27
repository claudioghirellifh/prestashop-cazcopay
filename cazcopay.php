<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


$configPath = _PS_MODULE_DIR_ . 'cazcopay/classes/CazcoPayConfig.php';
$loggerPath = _PS_MODULE_DIR_ . 'cazcopay/classes/CazcoPayLogger.php';
$apiClientPath = _PS_MODULE_DIR_ . 'cazcopay/classes/CazcoPayApiClient.php';

if (!is_file($configPath)) {
    $configPath = __DIR__ . '/classes/CazcoPayConfig.php';
}
if (!is_file($loggerPath)) {
    $loggerPath = __DIR__ . '/classes/CazcoPayLogger.php';
}
if (!is_file($apiClientPath)) {
    $apiClientPath = __DIR__ . '/classes/CazcoPayApiClient.php';
}

if (!is_file($configPath)) {
    error_log('[CazcoPay] Config path not found: ' . $configPath);
} else {
    require_once $configPath;
}

if (!is_file($loggerPath)) {
    error_log('[CazcoPay] Logger path not found: ' . $loggerPath);
} else {
    require_once $loggerPath;
}
if (!is_file($apiClientPath)) {
    error_log('[CazcoPay] ApiClient path not found: ' . $apiClientPath);
} else {
    require_once $apiClientPath;
}

class CazcoPay extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'cazcopay';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'Cazco Pay';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Cazco Pay');
        $this->description = $this->l('Aceite pagamentos com Cazco Pay (PIX, Boleto e Cartão).');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '9.99.99',
        ];

        if ($this->id && !$this->isRegisteredInHook('displayOrderDetail')) {
            $this->registerHook('displayOrderDetail');
        }
        if ($this->id && !$this->isRegisteredInHook('moduleRoutes')) {
            $this->registerHook('moduleRoutes');
        }
    }

    public function install()
    {
        CazcoPayLogger::log('Instalando módulo Cazco Pay');

        $installed = parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('moduleRoutes')
            && CazcoPayConfig::installDefaults()
            && $this->installOrderStates()
            && $this->installTables();

        if ($installed) {
            $this->ensureWebhookSecret();
        }

        return $installed;
    }

    public function uninstall()
    {
        CazcoPayLogger::log('Desinstalando módulo Cazco Pay');

        return $this->uninstallTables()
            && $this->uninstallOrderStates()
            && CazcoPayConfig::remove()
            && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitCazcoPayConfig')) {
            $env = Tools::getValue(CazcoPayConfig::KEY_ENV);
            $sbSk = Tools::getValue(CazcoPayConfig::KEY_SB_SK);
            $sbPk = Tools::getValue(CazcoPayConfig::KEY_SB_PK);
            $pdSk = Tools::getValue(CazcoPayConfig::KEY_PD_SK);
            $pdPk = Tools::getValue(CazcoPayConfig::KEY_PD_PK);
            $enablePix = (int) Tools::getValue(CazcoPayConfig::KEY_ENABLE_PIX);
            $enableBoleto = (int) Tools::getValue(CazcoPayConfig::KEY_ENABLE_BOLETO);
            $enableCard = (int) Tools::getValue(CazcoPayConfig::KEY_ENABLE_CARD);
            $documentSource = Tools::getValue(
                CazcoPayConfig::KEY_DOCUMENT_SOURCE,
                CazcoPayConfig::getDocumentSource()
            );
            if (
                !in_array($documentSource, ['auto', 'customer_dni', 'address_dni', 'address_vat', 'cbcz_customer', 'cbcz_address'], true)
                && strpos($documentSource, 'cbcz_customer:') !== 0
                && strpos($documentSource, 'cbcz_address:') !== 0
            ) {
                $documentSource = 'auto';
            }
            $installmentsMax = (int) Tools::getValue(CazcoPayConfig::KEY_INSTALLMENTS_MAX, CazcoPayConfig::getInstallmentsMax());
            $installmentsMax = max(1, min(12, $installmentsMax));

            Configuration::updateValue(CazcoPayConfig::KEY_ENV, $env);
            Configuration::updateValue(CazcoPayConfig::KEY_SB_SK, $sbSk);
            Configuration::updateValue(CazcoPayConfig::KEY_SB_PK, $sbPk);
            Configuration::updateValue(CazcoPayConfig::KEY_PD_SK, $pdSk);
            Configuration::updateValue(CazcoPayConfig::KEY_PD_PK, $pdPk);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_PIX, $enablePix);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_BOLETO, $enableBoleto);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_CARD, $enableCard);
            Configuration::updateValue(CazcoPayConfig::KEY_DOCUMENT_SOURCE, $documentSource);
            Configuration::updateValue(CazcoPayConfig::KEY_INSTALLMENTS_MAX, $installmentsMax);

            for ($i = 1; $i <= 12; $i++) {
                $interestKey = CazcoPayConfig::getInstallmentInterestKey($i);
                $minKey = CazcoPayConfig::getInstallmentMinKey($i);
                $interest = $this->sanitizeDecimal(
                    Tools::getValue($interestKey, Configuration::get($interestKey))
                );
                $minValue = $this->sanitizeDecimal(
                    Tools::getValue($minKey, Configuration::get($minKey))
                );
                Configuration::updateValue($interestKey, $interest);
                Configuration::updateValue($minKey, $minValue);
            }

            CazcoPayLogger::log('Configurações salvas', 1);
            $output .= $this->displayConfirmation($this->l('Configurações atualizadas com sucesso.'));
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $webhookUrl = $this->getWebhookFallbackUrl();
        $webhookInfoHtml = sprintf(
            '<div class="alert alert-info"><p>%s</p><p><code>%s</code></p></div>',
            $this->l('Informe esta URL no painel da Cazco Pay para receber os postbacks:'),
            Tools::safeOutput($webhookUrl)
        );

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configurações do Cazco Pay'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'html',
                        'name' => 'webhook_info',
                        'html_content' => $webhookInfoHtml,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Ambiente'),
                        'name' => CazcoPayConfig::KEY_ENV,
                        'options' => [
                            'query' => [
                                ['id' => 'sandbox', 'name' => $this->l('Sandbox')],
                                ['id' => 'production', 'name' => $this->l('Produção')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Selecione qual ambiente usar nas transações.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sandbox Secret Key (SK)'),
                        'name' => CazcoPayConfig::KEY_SB_SK,
                        'required' => false,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sandbox Public Key (PK)'),
                        'name' => CazcoPayConfig::KEY_SB_PK,
                        'required' => false,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Produção Secret Key (SK)'),
                        'name' => CazcoPayConfig::KEY_PD_SK,
                        'required' => false,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Produção Public Key (PK)'),
                        'name' => CazcoPayConfig::KEY_PD_PK,
                        'required' => false,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Mapeamento do documento do cliente'),
                        'name' => CazcoPayConfig::KEY_DOCUMENT_SOURCE,
                        'options' => [
                            'query' => $this->buildDocumentSourceOptions($defaultLang),
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Selecione de qual campo o documento será lido para enviar à Cazco Pay.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Habilitar PIX'),
                        'name' => CazcoPayConfig::KEY_ENABLE_PIX,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Sim'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Não'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Habilitar Boleto'),
                        'name' => CazcoPayConfig::KEY_ENABLE_BOLETO,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Sim'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Não'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Habilitar Cartão'),
                        'name' => CazcoPayConfig::KEY_ENABLE_CARD,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Sim'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Não'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Máximo de parcelas exibidas'),
                        'name' => CazcoPayConfig::KEY_INSTALLMENTS_MAX,
                        'options' => [
                            'query' => $this->buildInstallmentsLimitOptions(),
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Quantidade máxima de parcelas para cartão (1 a 12).'),
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Configuração de parcelas'),
                        'name' => 'installments_table',
                        'html_content' => $this->renderInstallmentsTable(),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Salvar'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $defaultLang;
        $helper->show_toolbar = false;
        $helper->submit_action = 'submitCazcoPayConfig';
        $helper->fields_value = [
            CazcoPayConfig::KEY_ENV => Configuration::get(CazcoPayConfig::KEY_ENV),
            CazcoPayConfig::KEY_SB_SK => Configuration::get(CazcoPayConfig::KEY_SB_SK),
            CazcoPayConfig::KEY_SB_PK => Configuration::get(CazcoPayConfig::KEY_SB_PK),
            CazcoPayConfig::KEY_PD_SK => Configuration::get(CazcoPayConfig::KEY_PD_SK),
            CazcoPayConfig::KEY_PD_PK => Configuration::get(CazcoPayConfig::KEY_PD_PK),
            CazcoPayConfig::KEY_DOCUMENT_SOURCE => CazcoPayConfig::getDocumentSource(),
            CazcoPayConfig::KEY_ENABLE_PIX => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_PIX),
            CazcoPayConfig::KEY_ENABLE_BOLETO => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_BOLETO),
            CazcoPayConfig::KEY_ENABLE_CARD => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_CARD),
            CazcoPayConfig::KEY_INSTALLMENTS_MAX => CazcoPayConfig::getInstallmentsMax(),
        ];
        for ($i = 1; $i <= 12; $i++) {
            $helper->fields_value[CazcoPayConfig::getInstallmentInterestKey($i)] = Tools::getValue(
                CazcoPayConfig::getInstallmentInterestKey($i),
                Configuration::get(CazcoPayConfig::getInstallmentInterestKey($i))
            );
            $helper->fields_value[CazcoPayConfig::getInstallmentMinKey($i)] = Tools::getValue(
                CazcoPayConfig::getInstallmentMinKey($i),
                Configuration::get(CazcoPayConfig::getInstallmentMinKey($i))
            );
        }

        return $helper->generateForm([$fieldsForm]);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            CazcoPayLogger::log('hookPaymentOptions: módulo inativo');
            return [];
        }

        $options = [];

        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $totalAmountCents = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100);
        $installmentsConfig = CazcoPayConfig::getInstallmentsConfig();

        // Variáveis comuns para templates inline
        $this->context->smarty->assign([
            'cart_total_cents' => $totalAmountCents,
            'currency_iso' => $currency->iso_code,
            'currency_sign' => $currency->sign,
            'installments_config' => $installmentsConfig,
            'installments_config_json' => json_encode(
                $installmentsConfig,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            ),
        ]);

        if (Configuration::get(CazcoPayConfig::KEY_ENABLE_PIX)) {
            $options[] = $this->buildOption('pix', $this->l('Pagar com PIX'),
                $this->fetch('module:cazcopay/views/templates/hook/option_pix.tpl'));
        }
        if (Configuration::get(CazcoPayConfig::KEY_ENABLE_BOLETO)) {
            $options[] = $this->buildOption('boleto', $this->l('Pagar com Boleto'),
                $this->fetch('module:cazcopay/views/templates/hook/option_boleto.tpl'));
        }
        if (Configuration::get(CazcoPayConfig::KEY_ENABLE_CARD)) {
            $options[] = $this->buildOption('card', $this->l('Pagar com Cartão'),
                $this->fetch('module:cazcopay/views/templates/hook/option_card.tpl'));
        }

        if (empty($options)) {
            CazcoPayLogger::log('Nenhum método de pagamento habilitado.');
        }

        CazcoPayLogger::log('hookPaymentOptions: opções geradas', 1, [
            'pix' => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_PIX),
            'boleto' => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_BOLETO),
            'card' => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_CARD),
            'count' => count($options),
        ]);

        return $options;
    }

    public function hookModuleRoutes($params)
    {
        $secret = $this->ensureWebhookSecret();

        return [
            'module-cazcopay-webhook' => [
                'controller' => 'webhook',
                'rule' => 'cazcopay/webhook/' . $secret,
                'keywords' => [
                    'token' => [
                        'regexp' => '[_A-Za-z0-9-]{8,}',
                    ],
                ],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                    'token' => $secret,
                ],
            ],
        ];
    }

    private function ensureWebhookSecret()
    {
        $secret = CazcoPayConfig::getWebhookSecret();

        if (!is_string($secret) || strlen($secret) < 16) {
            $secret = CazcoPayConfig::refreshWebhookSecret();
            CazcoPayLogger::log('Webhook secret regenerado automaticamente.', 1);
        }

        return $secret;
    }

    // Friendly URL enabling removed to avoid altering global rewrite settings.

    private function getWebhookUrl()
    {
        $secret = $this->ensureWebhookSecret();
        $url = $this->context->link->getModuleLink(
            $this->name,
            'webhook',
            ['token' => $secret],
            true
        );

        if (Configuration::get('PS_REWRITING_SETTINGS')) {
            $queryPos = strpos($url, '?');
            if (false !== $queryPos) {
                $url = substr($url, 0, $queryPos);
            }
        }

        return $url;
    }

    private function getWebhookFallbackUrl()
    {
        $secret = $this->ensureWebhookSecret();
        $baseUrl = rtrim($this->context->shop->getBaseURL(true), '/');

        return sprintf(
            '%s/index.php?fc=module&module=%s&controller=webhook&token=%s',
            $baseUrl,
            $this->name,
            $secret
        );
    }

    private function buildOption($method, $label, $additionalInformation)
    {
        $option = new PaymentOption();
        $option->setModuleName($this->name);
        $option->setCallToActionText('Cazco Pay — ' . $label);
        // Mantemos uma ação (controller) para a etapa de submissão; por ora usa o front/payment
        $option->setAction($this->context->link->getModuleLink($this->name, 'payment', ['method' => $method], true));
        // Mostra detalhes inline ao selecionar, como nos métodos nativos (cheque/transferência)
        $option->setAdditionalInformation($additionalInformation);

        return $option;
    }

    private function buildInstallmentsLimitOptions()
    {
        $list = [];
        for ($i = 1; $i <= 12; $i++) {
            $list[] = [
                'id' => $i,
                'name' => sprintf('%dx', $i),
            ];
        }

        return $list;
    }

    private function buildDocumentSourceOptions($idLang)
    {
        $options = [
            ['id' => 'auto', 'name' => $this->l('Automático (DNI, depois VAT)')],
            ['id' => 'customer_dni', 'name' => $this->l('Cliente: DNI')],
            ['id' => 'address_dni', 'name' => $this->l('Endereço: DNI')],
            ['id' => 'address_vat', 'name' => $this->l('Endereço: VAT/CPF/CNPJ')],
        ];

        $customerFields = $this->fetchCadastroBrasilFields('customer', $idLang);
        foreach ($customerFields as $field) {
            $label = $field['label'] ?: $field['field_key'];
            $validation = $field['validation_type'] ? strtoupper($field['validation_type']) : '';
            $name = $validation ? sprintf('Cliente: %s (%s)', $label, $validation) : sprintf('Cliente: %s', $label);
            $options[] = [
                'id' => 'cbcz_customer:' . $field['field_key'],
                'name' => $name,
            ];
        }

        $addressFields = $this->fetchCadastroBrasilFields('address', $idLang);
        foreach ($addressFields as $field) {
            $label = $field['label'] ?: $field['field_key'];
            $validation = $field['validation_type'] ? strtoupper($field['validation_type']) : '';
            $name = $validation ? sprintf('Endereço: %s (%s)', $label, $validation) : sprintf('Endereço: %s', $label);
            $options[] = [
                'id' => 'cbcz_address:' . $field['field_key'],
                'name' => $name,
            ];
        }

        return $options;
    }

    private function fetchCadastroBrasilFields($location, $idLang)
    {
        if (!in_array($location, ['customer', 'address'], true)) {
            return [];
        }
        $hasTable = $this->hasCadastroBrasilFieldTable();
        if (!$hasTable) {
            return [];
        }

        $sql = new DbQuery();
        $sql->select('f.field_key, f.validation_type, fl.label');
        $sql->from('cadastrobrasilcazco_field', 'f');
        $sql->leftJoin(
            'cadastrobrasilcazco_field_lang',
            'fl',
            'f.id_cadastrobrasilcazco_field = fl.id_cadastrobrasilcazco_field AND fl.id_lang=' . (int) $idLang
        );
        $sql->where('f.location="' . pSQL($location) . '"');
        $sql->orderBy('f.position ASC, f.id_cadastrobrasilcazco_field ASC');

        $rows = Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : [];
    }

    private function hasCadastroBrasilFieldTable()
    {
        $table = _DB_PREFIX_ . 'cadastrobrasilcazco_field';
        $exists = Db::getInstance()->getValue(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = "' . pSQL($table) . '"'
        );
        return (bool) $exists;
    }

    private function renderInstallmentsTable()
    {
        $rows = '';
        for ($i = 1; $i <= 12; $i++) {
            $interestKey = CazcoPayConfig::getInstallmentInterestKey($i);
            $interestValue = Tools::getValue($interestKey, Configuration::get($interestKey));

            $rows .= sprintf(
                '<tr><td>%1$dx</td>'
                . '<td><input type="text" class="form-control cazco-installment-interest" name="%2$s" value="%3$s" placeholder="0,00" /></td>'
                . '<td><input type="text" class="form-control cazco-installment-min" name="%4$s" value="%5$s" placeholder="0,00" /></td></tr>',
                $i,
                pSQL($interestKey),
                Tools::safeOutput($interestValue),
                pSQL(CazcoPayConfig::getInstallmentMinKey($i)),
                Tools::safeOutput(Tools::getValue(
                    CazcoPayConfig::getInstallmentMinKey($i),
                    Configuration::get(CazcoPayConfig::getInstallmentMinKey($i))
                ))
            );
        }

        $thead = '<thead><tr><th>' . $this->l('Parcelas') . '</th><th>' . $this->l('Juros (%)') . '</th><th>' . $this->l('Valor mínimo (R$)') . '</th></tr></thead>';
        $help = '<p class="help-block">' . $this->l('Informe o juros percentual e o valor mínimo do pedido para cada parcela. Apenas até o limite definido acima serão exibidos no checkout.') . '</p>';
        $script = '<script>(function(){'
            . 'function normalizePercent(input){var raw=(input.value||\"\").replace(/,/g,\".\");var num=parseFloat(raw);if(isNaN(num)){num=0;}input.value=num.toFixed(2).replace(\".\",\",\");}'
            . 'function normalizeCurrency(input){var raw=(input.value||\"\").replace(/\\./g,\"\").replace(/,/g,\".\");var num=parseFloat(raw);if(isNaN(num)){num=0;}input.value=num.toLocaleString(\"pt-BR\",{minimumFractionDigits:2,maximumFractionDigits:2});}'
            . 'function attach(selector, formatter){document.querySelectorAll(selector).forEach(function(el){el.addEventListener(\"blur\",function(){formatter(el);});});}'
            . 'if(typeof document!==\"undefined\"){if(document.readyState!==\"loading\"){attach(\".cazco-installment-interest\",normalizePercent);attach(\".cazco-installment-min\",normalizeCurrency);}else{document.addEventListener(\"DOMContentLoaded\",function(){attach(\".cazco-installment-interest\",normalizePercent);attach(\".cazco-installment-min\",normalizeCurrency);});}}'
            . '})();</script>';

        return '<table class="table table-bordered cazcopay-installments-table">' . $thead . '<tbody>' . $rows . '</tbody></table>' . $help . $script;
    }

    private function sanitizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $normalized = str_replace(',', '.', (string) $value);
        if (!is_numeric($normalized)) {
            return '0.00';
        }

        return sprintf('%.4F', (float) $normalized);
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        /** @var Order $order */
        $order = isset($params['order']) ? $params['order'] : ($params['objOrder'] ?? null);
        if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $pixData = $this->getPixData((int) $order->id);
        $currency = new Currency((int) $order->id_currency);

        $this->smarty->assign([
            'shop_name' => $this->context->shop->name,
            'cazco_order' => $pixData,
            'currency_sign' => $currency->sign,
            'order_reference' => $order->reference,
        ]);

        return $this->fetch('module:cazcopay/views/templates/hook/payment_return.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        if (!$this->active) {
            return '';
        }

        $order = null;
        if (!empty($params['order']) && $params['order'] instanceof Order) {
            $order = $params['order'];
        } elseif (!empty($params['order']['id'])) {
            $order = new Order((int) $params['order']['id']);
        } elseif (Tools::getValue('id_order')) {
            $order = new Order((int) Tools::getValue('id_order'));
        }

        if (!$order instanceof Order || !Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $pixData = $this->getPixData((int) $order->id);
        if (!$pixData || $pixData['payment_method'] !== 'pix') {
            return '';
        }

        $pixState = (int) Configuration::get(CazcoPayConfig::KEY_OS_PIX);
        if ((int) $order->current_state !== $pixState) {
            return '';
        }

        $currency = new Currency((int) $order->id_currency);

        $this->smarty->assign([
            'cazco_order' => $pixData,
            'currency_sign' => $currency->sign,
            'order_reference' => $order->reference,
        ]);

        return $this->fetch('module:cazcopay/views/templates/hook/order_detail_pix.tpl');
    }

    public function ensurePixOrderState()
    {
        $idState = (int) Configuration::get(CazcoPayConfig::KEY_OS_PIX);
        if ($idState && Validate::isLoadedObject(new OrderState($idState))) {
            return $idState;
        }

        if ($this->installOrderStates()) {
            $idState = (int) Configuration::get(CazcoPayConfig::KEY_OS_PIX);
            if ($idState && Validate::isLoadedObject(new OrderState($idState))) {
                return $idState;
            }
        }

        return 0;
    }

    protected function installOrderStates()
    {
        $idState = (int) Configuration::get(CazcoPayConfig::KEY_OS_PIX);
        if ($idState && Validate::isLoadedObject(new OrderState($idState))) {
            return true;
        }

        $orderState = new OrderState();
        $orderState->color = '#3429A8';
        $orderState->module_name = $this->name;
        $orderState->send_email = false;
        $orderState->hidden = false;
        $orderState->logable = false;
        $orderState->delivery = false;
        $orderState->shipped = false;
        $orderState->invoice = false;
        $orderState->paid = false;
        $orderState->unremovable = true;
        $orderState->template = '';

        foreach (Language::getLanguages(false) as $lang) {
            $orderState->name[(int) $lang['id_lang']] = $this->l('Aguardando pagamento PIX', 'cazcopay');
        }

        if (!$orderState->add()) {
            return false;
        }

        Configuration::updateValue(CazcoPayConfig::KEY_OS_PIX, (int) $orderState->id);

        return true;
    }

    protected function uninstallOrderStates()
    {
        $idState = (int) Configuration::get(CazcoPayConfig::KEY_OS_PIX);
        if ($idState) {
            $orderState = new OrderState($idState);
            if (Validate::isLoadedObject($orderState) && $orderState->module_name === $this->name) {
                $orderState->delete();
            }
        }

        return true;
    }

    protected function installTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cazcopay_order` (
            `id_cazcopay_order` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `payment_method` VARCHAR(32) NOT NULL,
            `transaction_id` VARCHAR(128) DEFAULT NULL,
            `pix_qrcode` TEXT DEFAULT NULL,
            `pix_url` TEXT DEFAULT NULL,
            `pix_expiration` DATETIME DEFAULT NULL,
            `amount` INT UNSIGNED DEFAULT NULL,
            `payload` LONGTEXT DEFAULT NULL,
            `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_cazcopay_order`),
            UNIQUE KEY `uniq_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        return Db::getInstance()->execute($sql);
    }

    protected function uninstallTables()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'cazcopay_order`');
    }

    public function savePixData($idOrder, array $data)
    {
        $expiration = null;
        if (!empty($data['expiration'])) {
            $timestamp = strtotime($data['expiration']);
            if ($timestamp !== false) {
                $expiration = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $row = [
            'id_order' => (int) $idOrder,
            'payment_method' => pSQL($data['payment_method'] ?? 'pix'),
            'transaction_id' => pSQL($data['transaction_id'] ?? ''),
            'pix_qrcode' => pSQL($data['qrcode'] ?? '', true),
            'pix_url' => pSQL($data['url'] ?? '', true),
            'pix_expiration' => $expiration,
            'amount' => isset($data['amount']) ? (int) $data['amount'] : null,
            'payload' => pSQL(isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : '', true),
        ];

        $existing = $this->getPixData($idOrder);
        if ($existing) {
            Db::getInstance()->update('cazcopay_order', $row, 'id_order = ' . (int) $idOrder);
        } else {
            $row['date_add'] = date('Y-m-d H:i:s');
            Db::getInstance()->insert('cazcopay_order', $row);
        }
    }

    public function getPixData($idOrder)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('cazcopay_order');
        $sql->where('id_order = ' . (int) $idOrder);

        $row = Db::getInstance()->getRow($sql);
        if (!$row) {
            return null;
        }

        $row['payload'] = $row['payload'] ? json_decode($row['payload'], true) : null;
        $row['qrcode_image'] = $row['pix_qrcode'] ? $this->buildPixQrCodeUrl($row['pix_qrcode']) : null;
        $row['pix_expiration_formatted'] = $row['pix_expiration']
            ? Tools::displayDate($row['pix_expiration'], null, true)
            : null;

        return $row;
    }

    protected function buildPixQrCodeUrl($payload)
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($payload);
    }
}
