<?php

namespace App\Services;

use App\Helpers\Logger;
use Exception;

class TokenService
{
    private string $subDomain;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $code;
    private string $tokenFile;
    private Logger $logger;

    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $expiresAt = null;

    public function __construct(array $config)
    {
        $this->subDomain = $config['sub_domain'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri = $config['redirect_uri'];
        $this->code = $config['code'];
        $this->tokenFile = $config['token_file'] ?? __DIR__ . '/../../logs/TOKEN.json';
        $this->logger = new Logger();

        $this->loadToken();
    }

    public function getAccessToken(): string
    {
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        return $this->accessToken ?? '';
    }

    private function isTokenExpired(): bool
    {
        return !$this->accessToken || ($this->expiresAt && $this->expiresAt < time());
    }

  
    private function loadToken(): void
    {
        if (!file_exists($this->tokenFile)) {
            $this->logger->info("Файл токена не найден, выполняется первичная авторизация...");
            $this->requestNewToken();
            return;
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);

        $this->accessToken = $tokenData['access_token'] ?? null;
        $this->refreshToken = $tokenData['refresh_token'] ?? null;
        $this->expiresAt = $tokenData['expires_at'] ?? null;
    }

    private function refreshToken(): void
    {
        if (!$this->refreshToken) {
            $this->logger->error("Отсутствует refresh_token — выполняется новая авторизация");
            $this->requestNewToken();
            return;
        }

        $url = "https://{$this->subDomain}.amocrm.ru/oauth2/access_token";

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'redirect_uri' => $this->redirectUri,
        ];

        $this->requestToken($url, $data);
    }

    private function requestNewToken(): void
    {
        $url = "https://{$this->subDomain}.amocrm.ru/oauth2/access_token";

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];

        $this->requestToken($url, $data);
    }

    /**
     * Общий метод отправки запроса и сохранения токена
     */
    private function requestToken(string $url, array $data): void
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode > 204) {
            throw new Exception("Ошибка запроса токена ($httpCode): " . $response);
        }

        $tokenData = json_decode($response, true);

        $this->accessToken = $tokenData['access_token'] ?? null;
        $this->refreshToken = $tokenData['refresh_token'] ?? null;
        $this->expiresAt = time() + ($tokenData['expires_in'] ?? 3600);

        $this->saveToken();
        $this->logger->info("Токен успешно обновлён");
    }

    private function saveToken(): void
    {
        $token = [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt,
        ];
        file_put_contents($this->tokenFile, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}