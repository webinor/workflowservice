<?php

namespace App\Services\User;


use Illuminate\Support\Facades\Http;

class UserServiceClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl =
            config('services.user_service.base_url');
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
     * Trouver un utilisateur
     * =========================================
     */
    public function find(int $userId)//: ?array
    {
        // return $userId;
        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/{$userId}"
        );

        if (!$response->successful()) {
            return null;
        }

        return $response->json()["user"];
    }

    /**
     * =========================================
     * Utilisateurs par rôle CODE
     * =========================================
     */
    public function usersByRole(
        string $roleCode
    ): array {

        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/roles/{$roleCode}/users"
        );

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * =========================================
     * Utilisateurs par rôle ID
     * =========================================
     */
    public function usersByRoleId(
        int $roleId
    ): array {

        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/roles/id/{$roleId}/users"
        );

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['data'] ?? [];
    }
}