<?php

namespace App\Services\Department;

use Exception;
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
     * Headers gateway.
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

    /**
     * =========================================
     * Employés ayant des responsabilités effectives
     * =========================================
     *
     * Récupère les employés possédant effectivement
     * au moins une des responsabilités demandées.
     *
     * Le Department Service doit calculer les responsabilités
     * effectives en tenant compte des exceptions GRANT et REVOKE.
     *
     * @param array $responsibilityCodes Codes des responsabilités
     * @return array Liste des employés concernés
     */
    public function employeesByResponsibilities(
        array $responsibilityCodes
    )//: array
     {

        if (empty($responsibilityCodes)) {
            return [];
        }

        $response = Http::withHeaders(
            $this->headers()
        )->get(
            "{$this->baseUrl}/employees/responsibility-members-multiple",
            [
                'responsibilities' => $responsibilityCodes,
            ]
        );

        if (!$response->successful()) {

                              throw new Exception(json_encode($response->body()));

            return [];
        }

        return $response->json()['data'] ?? [];
    }
}