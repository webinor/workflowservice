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

    /**
 * =========================================
 * Annuler les transactions financière d'un document
 * =========================================
 *
 * @param string    $documentUuid Identifiant de la 
 * @param int    $userId        Utilisateur qui effectue l'annulation
 * @param string $reason        Motif de l'annulation
 *
 * @return array|null
 */
public function cancelTransactions(
    string $documentUuid,
    int $userId,
    string $reason
): ?array {
    $response = Http::withHeaders(
        $this->headers()
    )->timeout(30)->post(
        "{$this->baseUrl}/transactions/by-document/{$documentUuid}/cancel",
        [
            'user_id' => $userId,
            'reason' => $reason,
        ]
    );

    if (!$response->successful()) {
        return null;
    }

    return $response->json();
}
}