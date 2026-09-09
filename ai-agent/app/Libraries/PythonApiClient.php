<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\Services;
use Throwable;

class PythonApiClient
{
    public function health(string $baseUrl, int $timeout): array
    {
        $url = rtrim($baseUrl, '/') . '/health';

        try {
            $response = $this->client($timeout)->get($url, [
                'http_errors' => false,
                'headers'     => ['Accept' => 'application/json'],
            ]);
        } catch (HTTPException | Throwable $e) {
            return $this->unavailable($e);
        }

        $status = $response->getStatusCode();
        $body   = $this->decode((string) $response->getBody());

        if ($status >= 200 && $status < 300 && ! empty($body['success'])) {
            return [
                'success'   => true,
                'connected' => true,
                'status'    => 'Connected',
                'data'      => $body,
            ];
        }

        return [
            'success'   => false,
            'connected' => false,
            'status'    => 'Disconnected',
            'message'   => 'Python API health check failed.',
        ];
    }

    public function process(string $baseUrl, int $timeout, array $payload): array
    {
        return $this->postJson($baseUrl, $timeout, '/numpy/process', $payload);
    }

    public function chat(string $baseUrl, int $timeout, array $payload): array
    {
        return $this->postJson($baseUrl, $timeout, '/chat/', $payload);
    }

    public function createArray(string $baseUrl, int $timeout, array $payload): array
    {
        return $this->postJson($baseUrl, $timeout, '/numpy/array', $payload);
    }

    public function reshape(string $baseUrl, int $timeout, array $payload): array
    {
        return $this->postJson($baseUrl, $timeout, '/numpy/reshape', $payload);
    }

    private function postJson(string $baseUrl, int $timeout, string $path, array $payload): array
    {
        $url = rtrim($baseUrl, '/') . $path;

        try {
            $response = $this->client($timeout)->post($url, [
                'http_errors' => false,
                'json'        => $payload,
                'headers'     => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (HTTPException | Throwable $e) {
            return $this->unavailable($e);
        }

        $status = $response->getStatusCode();
        $body   = $this->decode((string) $response->getBody());

        if ($status >= 200 && $status < 300 && ! empty($body['success'])) {
            return $body;
        }

        $message = $body['error'] ?? $this->statusMessage($status);

        return [
            'success' => false,
            'error'   => $message,
        ];
    }

    private function client(int $timeout)
    {
        return Services::curlrequest([
            'timeout'         => $timeout,
            'connect_timeout' => min(10, $timeout),
            'http_errors'     => false,
        ]);
    }

    private function decode(string $raw): array
    {
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function unavailable(Throwable $e): array
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'timed out') || str_contains($raw, 'timeout')) {
            return [
                'success'   => false,
                'connected' => false,
                'status'    => 'Disconnected',
                'error'     => 'The Python API request timed out. Try again or increase the timeout in Settings.',
            ];
        }

        return [
            'success'   => false,
            'connected' => false,
            'status'    => 'Disconnected',
            'error'     => 'Python API is unavailable. Start the Python server and check the API URL in Settings.',
        ];
    }

    private function statusMessage(int $status): string
    {
        return match (true) {
            $status === 408, $status === 504 => 'The Python API request timed out.',
            $status >= 500 => 'A Python/NumPy processing error occurred.',
            default => 'The Python API could not complete this request.',
        };
    }
}
