<?php

class CazcoPayLogger
{
    /**
     * Adiciona um log no PrestaShop.
     *
     * @param string $message
     * @param int $severity 1=Informativo, 2=Aviso, 3=Erro
     * @param array $context
     */
    public static function log($message, $severity = 1, array $context = [])
    {
        // Evitar logar dados sensíveis
        $safe = $message;
        if (!empty($context)) {
            $safe .= ' | ' . json_encode(self::sanitize($context));
        }

        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog($safe, $severity, null, 'CazcoPay', null, true);
        } else {
            Logger::addLog($safe, $severity, null, 'CazcoPay', null, true);
        }
    }

    private static function sanitize(array $context)
    {
        $blocked = ['secret', 'secret_key', 'authorization', 'password', 'token'];
        $out = [];
        foreach ($context as $k => $v) {
            if (in_array(strtolower($k), $blocked, true)) {
                $out[$k] = '***';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}

