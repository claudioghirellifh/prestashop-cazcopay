<?php

class CazcoPayApiClient
{
    private $baseUrl;
    private $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(\CazcoPayConfig::getBaseUrl(), '/');
        $this->secretKey = \CazcoPayConfig::getSecretKey();
    }

    /**
     * @throws Exception
     */
    public function createTransaction(array $payload)
    {
        if (empty($this->secretKey)) {
            throw new Exception('Secret key não configurada.');
        }

        $endpoint = $this->baseUrl . '/transactions';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new Exception('Falha ao codificar payload JSON: ' . json_last_error_msg());
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->secretKey . ':x'),
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro de comunicação com a API: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Resposta inválida da API (JSON): ' . $response);
        }

        if ($httpCode >= 400) {
            $message = isset($decoded['message']) ? $decoded['message'] : 'Erro desconhecido';
            throw new Exception('API retornou erro (' . $httpCode . '): ' . $message);
        }

        return [
            'http_code' => $httpCode,
            'body' => $decoded,
        ];
    }
}
