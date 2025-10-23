<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


$configPath = _PS_MODULE_DIR_ . 'cazcopay/classes/CazcoPayConfig.php';
$loggerPath = _PS_MODULE_DIR_ . 'cazcopay/classes/CazcoPayLogger.php';

if (!is_file($configPath)) {
    $configPath = __DIR__ . '/classes/CazcoPayConfig.php';
}
if (!is_file($loggerPath)) {
    $loggerPath = __DIR__ . '/classes/CazcoPayLogger.php';
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
    }

    public function install()
    {
        CazcoPayLogger::log('Instalando módulo Cazco Pay');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && CazcoPayConfig::installDefaults();
    }

    public function uninstall()
    {
        CazcoPayLogger::log('Desinstalando módulo Cazco Pay');

        return parent::uninstall() && CazcoPayConfig::remove();
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

            Configuration::updateValue(CazcoPayConfig::KEY_ENV, $env);
            Configuration::updateValue(CazcoPayConfig::KEY_SB_SK, $sbSk);
            Configuration::updateValue(CazcoPayConfig::KEY_SB_PK, $sbPk);
            Configuration::updateValue(CazcoPayConfig::KEY_PD_SK, $pdSk);
            Configuration::updateValue(CazcoPayConfig::KEY_PD_PK, $pdPk);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_PIX, $enablePix);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_BOLETO, $enableBoleto);
            Configuration::updateValue(CazcoPayConfig::KEY_ENABLE_CARD, $enableCard);

            CazcoPayLogger::log('Configurações salvas', 1);
            $output .= $this->displayConfirmation($this->l('Configurações atualizadas com sucesso.'));
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configurações do Cazco Pay'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
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
            CazcoPayConfig::KEY_ENABLE_PIX => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_PIX),
            CazcoPayConfig::KEY_ENABLE_BOLETO => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_BOLETO),
            CazcoPayConfig::KEY_ENABLE_CARD => (int) Configuration::get(CazcoPayConfig::KEY_ENABLE_CARD),
        ];

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

        // Variáveis comuns para templates inline
        $this->context->smarty->assign([
            'cart_total_cents' => $totalAmountCents,
            'currency_iso' => $currency->iso_code,
            'currency_sign' => $currency->sign,
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

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }
        $this->smarty->assign([
            'shop_name' => $this->context->shop->name,
        ]);
        return $this->fetch('module:cazcopay/views/templates/hook/payment_return.tpl');
    }
}
