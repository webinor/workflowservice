<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DepartmentContextService
{
    protected HttpClientService $http;

    public function __construct()
    {
        $this->http = HttpClientService::service('department');
    }

    public function getEmployeeContextMap(array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(
            array_filter($employeeIds)
        ));

        if (empty($employeeIds)) {
            return [];
        }

        $response = $this->http->post(
            'employees/employee-context/batch',
            [
                'employee_ids' => $employeeIds,
            ]
        );

        if (!$response['success']) {
            Log::warning('Employee context batch failed', [
                'employee_ids' => $employeeIds,
                'response' => $response,
            ]);
        
        throw new \Exception("Error Processing Request : employees/employee-context/batch ; DepartmentContextService", 1);
        

            return [];
        }

        return $response['data'] ?? [];
    }
}