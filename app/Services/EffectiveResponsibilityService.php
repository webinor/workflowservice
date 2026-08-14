<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class EffectiveResponsibilityService
{
    /**
     * Récupère les responsabilités effectives d'un employé.
     *
     * Les responsabilités sont calculées par le Department Service
     * en tenant compte :
     *
     * - des responsabilités du poste/rôle
     * - des GRANT de l'affectation
     * - des REVOKE de l'affectation
     *
     * @param int $employeeId
     * @return array
     * @throws Exception
     */
    public function getForEmployee(int $employeeId , $employeeContext = null): array
    {

        if (!$employeeContext) {
            # code...
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

        public function getContext(int $employeeId): array
    {
        return $this->getEmployeeContext($employeeId);
    }

    /**
     * Récupère le contexte de l'employé depuis le Department Service.
     *
     * @param int $employeeId
     * @return array
     * @throws Exception
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