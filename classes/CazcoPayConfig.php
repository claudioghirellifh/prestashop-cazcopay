<?php

class CazcoPayConfig
{
    public const KEY_ENV = 'CAZCO_ENV'; // sandbox|production
    public const KEY_SB_SK = 'CAZCO_SANDBOX_SK';
    public const KEY_SB_PK = 'CAZCO_SANDBOX_PK';
    public const KEY_PD_SK = 'CAZCO_PROD_SK';
    public const KEY_PD_PK = 'CAZCO_PROD_PK';
    public const KEY_ENABLE_PIX = 'CAZCO_ENABLE_PIX';
    public const KEY_ENABLE_BOLETO = 'CAZCO_ENABLE_BOLETO';
    public const KEY_ENABLE_CARD = 'CAZCO_ENABLE_CARD';

    public static function installDefaults()
    {
        $ok = true;
        $ok = $ok && Configuration::updateValue(self::KEY_ENV, 'sandbox');
        $ok = $ok && Configuration::updateValue(self::KEY_SB_SK, '');
        $ok = $ok && Configuration::updateValue(self::KEY_SB_PK, '');
        $ok = $ok && Configuration::updateValue(self::KEY_PD_SK, '');
        $ok = $ok && Configuration::updateValue(self::KEY_PD_PK, '');
        $ok = $ok && Configuration::updateValue(self::KEY_ENABLE_PIX, 1);
        $ok = $ok && Configuration::updateValue(self::KEY_ENABLE_BOLETO, 1);
        $ok = $ok && Configuration::updateValue(self::KEY_ENABLE_CARD, 1);
        return $ok;
    }

    public static function remove()
    {
        $ok = true;
        foreach ([
            self::KEY_ENV,
            self::KEY_SB_SK,
            self::KEY_SB_PK,
            self::KEY_PD_SK,
            self::KEY_PD_PK,
            self::KEY_ENABLE_PIX,
            self::KEY_ENABLE_BOLETO,
            self::KEY_ENABLE_CARD,
        ] as $key) {
            $ok = $ok && Configuration::deleteByName($key);
        }
        return $ok;
    }

    public static function getEnv()
    {
        $env = (string) Configuration::get(self::KEY_ENV);
        return in_array($env, ['sandbox', 'production']) ? $env : 'sandbox';
    }

    public static function getSecretKey()
    {
        return self::getEnv() === 'sandbox'
            ? (string) Configuration::get(self::KEY_SB_SK)
            : (string) Configuration::get(self::KEY_PD_SK);
    }

    public static function getPublicKey()
    {
        return self::getEnv() === 'sandbox'
            ? (string) Configuration::get(self::KEY_SB_PK)
            : (string) Configuration::get(self::KEY_PD_PK);
    }

    public static function getBaseUrl()
    {
        return self::getEnv() === 'sandbox'
            ? 'https://api.sandbox.cazcopay.com/v1'
            : 'https://api.cazcopay.com/v1';
    }

}
