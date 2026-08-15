<?php

namespace App\Services\Actor;

use Exception;
use Illuminate\Support\Facades\Http;

class ActorServiceClient
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
     * Trouver un employé
     * =========================================
     */
    public function findEmployee(int $employeeCode): ?array
    {
        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/employees/{$employeeCode}"
        );

        if (!$response->successful()) {

            throw new Exception(json_encode($response->body()), 1);

            return null;
        }

        return $response->json()['employee'] ?? null;
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
            "{$this->baseUrl}/by-role/{$roleCode}"
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