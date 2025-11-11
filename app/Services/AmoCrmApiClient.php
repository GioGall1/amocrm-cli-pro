<?php

namespace App\Services;

use App\Services\LoggerService;
use Exception;

class AmoCrmApiClient
{
    private string $baseUrl;
    private string $accessToken;
    private LoggerService $logger;

    public function __construct(string $subDomain, string $accessToken)
    {
        $this->baseUrl = "https://{$subDomain}.amocrm.ru/api/v4/";
        $this->accessToken = $accessToken;
        $this->logger = new LoggerService();
    }

    /**
     * Выполняет GET-запрос к API
     */
    public function get(string $endpoint, array $params = []): ?array
    {
        $url = $this->baseUrl . $endpoint . ($params ? '?' . http_build_query($params) : '');
        return $this->request('GET', $url);
    }

    /**
     * Выполняет POST/PATCH-запрос к API
     */
    public function send(string $endpoint, array $data, string $method = 'POST'): ?array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->request($method, $url, $data);
    }

    /**
     * Базовый HTTP-запрос
     */
    private function request(string $method, string $url, array $data = []): ?array
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: application/json",
            ],
        ];

        if (in_array($method, ['POST', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            $error = curl_error($curl) ?: "HTTP $httpCode";
            $this->logger->error("Ошибка запроса {$method} {$url}: {$error}");
            curl_close($curl);
            throw new Exception("AmoCRM API error: {$error}");
        }

        curl_close($curl);
        $this->logger->info("Успешный запрос {$method} {$url}");
        return json_decode($response, true);
    }
}