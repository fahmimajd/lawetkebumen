<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class WaGatewayClient
{
    private Client $client;
    private string $token;

    public function __construct()
    {
        $sendUrl = (string) config('services.wa_gateway.send_url');
        $baseUrl = (string) config('services.wa_gateway.base_url', '');
        $timeout = (int) config('services.wa_gateway.timeout', 5);

        $resolvedBase = $baseUrl !== '' ? $baseUrl : $this->deriveBaseUrl($sendUrl);

        $this->client = new Client([
            'base_uri' => rtrim($resolvedBase, '/').'/',
            'timeout' => $timeout,
            'http_errors' => false,
        ]);

        $this->token = (string) config('services.wa_gateway.token');

        if ($this->token === '') {
            throw new RuntimeException('WA gateway token is not configured.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return $this->requestJson('GET', 'status');
    }

    /**
     * @return array<string, mixed>
     */
    public function getQr(): array
    {
        return $this->requestJson('GET', 'qr');
    }

    /**
     * @return array<string, mixed>
     */
    public function reconnect(): array
    {
        return $this->requestJson('POST', 'reconnect');
    }

    /**
     * @return array<string, mixed>
     */
    public function logout(): array
    {
        return $this->requestJson('POST', 'logout');
    }

    /**
     * @return array<string, mixed>
     */
    public function reset(): array
    {
        return $this->requestJson('POST', 'reset');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $uri): array
    {
        try {
            $response = $this->client->request($method, $uri, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to reach WA gateway.', 0, $exception);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $payload = json_decode($body, true);

        if ($status >= 400 || ! is_array($payload)) {
            $message = is_array($payload) && isset($payload['message'])
                ? (string) $payload['message']
                : 'WA gateway returned an error.';
            throw new RuntimeException($message);
        }

        return $payload;
    }

    private function deriveBaseUrl(string $sendUrl): string
    {
        $parsed = parse_url($sendUrl);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new RuntimeException('WA gateway URL is not configured.');
        }

        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return sprintf('%s://%s%s', $parsed['scheme'], $parsed['host'], $port);
    }
}
