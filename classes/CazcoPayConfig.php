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
    public const KEY_INSTALLMENTS_MAX = 'CAZCO_INSTALLMENTS_MAX';

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
        $ok = $ok && Configuration::updateValue(self::KEY_INSTALLMENTS_MAX, 12);

        for ($i = 1; $i <= 12; $i++) {
            $ok = $ok && Configuration::updateValue(self::getInstallmentInterestKey($i), '0.00');
            $ok = $ok && Configuration::updateValue(self::getInstallmentMinKey($i), '0.00');
        }
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
            self::KEY_INSTALLMENTS_MAX,
        ] as $key) {
            $ok = $ok && Configuration::deleteByName($key);
        }
        for ($i = 1; $i <= 12; $i++) {
            $ok = $ok && Configuration::deleteByName(self::getInstallmentInterestKey($i));
            $ok = $ok && Configuration::deleteByName(self::getInstallmentMinKey($i));
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

    public static function getInstallmentsMax()
    {
        $max = (int) Configuration::get(self::KEY_INSTALLMENTS_MAX);
        if ($max < 1) {
            $max = 1;
        }
        if ($max > 12) {
            $max = 12;
        }

        return $max;
    }

    public static function getInstallmentsConfig()
    {
        $max = self::getInstallmentsMax();
        $config = [];
        for ($i = 1; $i <= $max; $i++) {
            $interest = (float) Configuration::get(self::getInstallmentInterestKey($i));
            $minAmount = (float) Configuration::get(self::getInstallmentMinKey($i));
            $config[] = [
                'number' => $i,
                'interest' => $interest,
                'min' => $minAmount,
            ];
        }

        return $config;
    }

    public static function getInstallmentInterestKey($n)
    {
        return 'CAZCO_INSTALLMENT_' . (int) $n . '_INTEREST';
    }

    public static function getInstallmentMinKey($n)
    {
        return 'CAZCO_INSTALLMENT_' . (int) $n . '_MIN';
    }
}
