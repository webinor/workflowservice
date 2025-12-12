<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

trait ResolveDepartmentValidator
{

    public function getDepartmentByUsers($userIds)
{
    $departmentServiceUrl = config("services.department_service.base_url");

    // Exemple d'URL finale : http://department-service/api/roles/5/department
    $url = "{$departmentServiceUrl}/by-users/";

    $response = Http::acceptJson()->post($url,[
            "user_ids" => $userIds
        ]);

    if (!$response->successful()) {
        $userIdsString = implode(', ', $userIds);

        throw new \Exception(
            "Impossible de récupérer le département pour les utilisateurs suivants : {$userIdsString}. " .
            "Status: {$response->status()}, Response: {$response->body()}"
        );
    }

    return $response->json()[0];
}

    public function resolveDepartmentValidator($departmentId)
    {
        $departmentServiceUrl = config("services.department_service.base_url"); // ex: http://workflow-service/api

        $response = Http::acceptJson()->get(
            "$departmentServiceUrl/{$departmentId}/hierarchie"
        );

        if (!$response->successful()) {
            //   throw new \Exception($response->body());

            throw new \Exception(
                "Impossible de récupérer la hiérarchie du département. " .
                    "Status: {$response->status()}, Response: {$response->body()}"
            );
        }

        return $hierarchie = $response->json();

        // Exemple de JSON attendu :
        // {
        //   "responsable": { "id": 12, "name": "Resp A" },
        //   "directeur_adjoint": { "id": 34, "name": "Adjoint B" },
        //   "directeur": { "id": 56, "name": "Directeur C" }
        // }

        if (!empty($hierarchie["responsable"])) {
            return $hierarchie["responsable"]["id"];
        }

        if (!empty($hierarchie["directeur_adjoint"])) {
            return $hierarchie["directeur_adjoint"]["id"];
        }

        if (!empty($hierarchie["directeur"])) {
            return $hierarchie["directeur"]["id"];
        }

        throw new \Exception("Aucun responsable trouvé pour ce département");
    }

    public function resolveRoleValidator($position)
    {
        $userServiceUrl = config("services.user_service.base_url"); // ex: http://workflow-service/api

        $response = Http::get("$userServiceUrl/roles/search?name={$position}");

        if (!$response->successful()) {
            throw new \Exception("Impossible de récupérer le role");
        }

        return $role = $response->json();

        // Exemple de JSON attendu :
        // {
        //   "responsable": { "id": 12, "name": "Resp A" },
        //   "directeur_adjoint": { "id": 34, "name": "Adjoint B" },
        //   "directeur": { "id": 56, "name": "Directeur C" }
        // }

        if (!empty($hierarchie["responsable"])) {
            return $hierarchie["responsable"]["id"];
        }

        if (!empty($hierarchie["directeur_adjoint"])) {
            return $hierarchie["directeur_adjoint"]["id"];
        }

        if (!empty($hierarchie["directeur"])) {
            return $hierarchie["directeur"]["id"];
        }

        throw new \Exception("Aucun responsable trouvé pour ce département");
    }

    public function getRoleValidator($departmentId)
    {
        $department_position = $this->resolveDepartmentValidator($departmentId);
        return $role = $this->resolveRoleValidator(
            $department_position["position"]["name"]
        )["results"];
    }
}
