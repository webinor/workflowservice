<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class HttpClientService
{
    protected string $baseUrl;
    protected ?string $token;

    protected static array $serviceMap = [
        'document' => 'services.document_service.base_url',
        'workflow' => 'services.workflow_service.base_url',
        'user'     => 'services.user_service.base_url',
    ];

    protected static string $defaultBaseUrl = '';

    public function __construct(string $baseUrl, ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token   = $token ?? $this->resolveToken();
    }

    protected function resolveToken(): ?string
    {
        if (!app()->runningInConsole() && request() && request()->bearerToken()) {
            return request()->bearerToken();
        }

        if (auth()->check() && method_exists(auth()->user(), 'currentAccessToken')) {
           // return auth()->user()?->currentAccessToken()?->token;
        }

        return null;
    }

    public static function service(string $key, ?string $token = null): self
    {
        $baseUrl = self::$serviceMap[$key] ? config(self::$serviceMap[$key]) : null;

        if (!$baseUrl) {
            $baseUrl = self::$defaultBaseUrl ?: env('API_BASE_URL', 'http://localhost:8000/api');
        }

        return new static($baseUrl, $token);
    }

    protected function request(string $method, string $uri, array $data = [])
    {
        try {
            $response = Http::acceptJson()
                ->withToken($this->token)
                ->{$method}("{$this->baseUrl}/{$uri}", $data);

            // Si échec HTTP → throw
            $response->throw();

            return [
                'success' => true,
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (RequestException $e) {
            // Log centralisé
            Log::error('HttpClientService Error', [
                'url'     => "{$this->baseUrl}/{$uri}",
                'method'  => strtoupper($method),
                'message' => $e->getMessage(),
                'body'    => $e->response ? $e->response->body() : null,
            ]);

            return [
                'success' => false,
                'status'  => $e->response?$e->response->status() : 500,
                'error'   => $e->getMessage(),
                'body'    => $e->response?$e->response->json() : null,
            ];
        }
    }

    public function get(string $uri, array $params = [])
    {
        return $this->request('get', $uri, $params);
    }

    public function post(string $uri, array $data = [])
    {
        return $this->request('post', $uri, $data);
    }

    public function put(string $uri, array $data = [])
    {
        return $this->request('put', $uri, $data);
    }

    public function delete(string $uri, array $data = [])
    {
        return $this->request('delete', $uri, $data);
    }
}
