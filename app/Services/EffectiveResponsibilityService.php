<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class EffectiveResponsibilityService
{
    /**
     * Récupère les responsabilités effectives d'un employé.
     */
    public function getForEmployee(
        int $employeeId,
        $employeeContext = null
    ): array {

        if (!$employeeContext) {
            $employeeContext = $this->getEmployeeContext($employeeId);
        }

        return collect(
            data_get($employeeContext, 'responsibilities', [])
        )
            ->pluck('code')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Récupère le contexte complet d'un employé.
     */
    public function getContext(int $employeeId): array
    {
        return $this->getEmployeeContext($employeeId);
    }

    /**
     * Récupère tous les employés possédant une responsabilité.
     */
    public function getEmployeesWithResponsibility(
        string $responsibilityCode,$employeeId
    ): array {

        $response = Http::withToken(
            request()->bearerToken()
        )
            ->acceptJson()
            ->get(
                config('services.department_service.base_url') .
                '/employees/by-responsibility',
                [
                    'responsibility' => $responsibilityCode,
                    'employeeId' => $employeeId,
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                $response->body(),
                $response->status()
            );
        }

        return $response->json('data', []);
    }

    /**
     * Récupère le contexte d'un employé depuis le Department Service.
     */
    protected function getEmployeeContext(int $employeeId): array
    {
        $response = Http::withToken(
            request()->bearerToken()
        )
            ->acceptJson()
            ->get(
                config('services.department_service.base_url') .
                '/employees/' .
                $employeeId .
                '/context'
            );

        if (!$response->successful()) {
            throw new Exception(
                $response->body(),
                $response->status()
            );
        }

        return $response->json();
    }
}