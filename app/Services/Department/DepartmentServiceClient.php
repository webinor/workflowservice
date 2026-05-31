<?php

namespace App\Services\Department;

use Illuminate\Support\Facades\Http;

class DepartmentServiceClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl =
            config('services.department_service.base_url');
    }

    /**
     * Headers gateway
     */
    protected function headers(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /**
     * =========================================
     * Responsable service
     * =========================================
     */
    public function serviceHead(
        string $serviceCode
    ): ?array {

        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/services/{$serviceCode}/head"
        );

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['data'] ?? null;
    }
}