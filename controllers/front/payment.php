<?php
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

        // Seleciona template por método
        $tplMap = [
            'pix' => 'payment_pix.tpl',
            'boleto' => 'payment_boleto.tpl',
            'card' => 'payment_card.tpl',
        ];
        $tpl = isset($tplMap[$method]) ? $tplMap[$method] : 'payment_boleto.tpl';
        $this->setTemplate('module:cazcopay/views/templates/front/' . $tpl);
    }
}
