<?php

namespace App\Services\Workflow;

use App\Services\EffectiveResponsibilityService;
use Exception;
use Illuminate\Support\Facades\Http;

class WorkflowDynamicResolverService
{
    protected EffectiveResponsibilityService $effectiveResponsibilityService;

    public function __construct(EffectiveResponsibilityService $effectiveResponsibilityService) {
        $this->effectiveResponsibilityService = $effectiveResponsibilityService;
    }

    /**
 * Résout l'acteur associé à un document.
 *
 * L'acteur peut être :
 * - un EMPLOYEE ;
 * - un DEPARTMENT.
 *
 * @param array $document
 * @return array|null
 */
public function resolveActor($document)
{
    $mapper = [
        "mission" => "actor_details",
        "purchase_request" => "actor_details",
        "taxi_paper" => "actor_details",
        "fee_note" => "actor_details",
        "absence_request" => "actor_details",
        "regularization_sheet" => "actor_details",
        "invoice_provider" => "actor_details",
    ];

    $slug = data_get($document, 'document_type.relation_name');

    /*
    |--------------------------------------------------------------------------
    | 1. Conserver les informations détaillées existantes.
    |--------------------------------------------------------------------------
    */
    if (
        isset($mapper[$slug])
        && !empty($document[$mapper[$slug]])
    ) {
        return $document[$mapper[$slug]];
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Repli sur actor_type et actor_id.
    |--------------------------------------------------------------------------
    */
    $actorType = data_get($document, 'actor_type');
    $actorId = data_get($document, 'actor_id');

    if (!$actorType || !$actorId) {
        return null;
    }

    return [
        'id' => $actorId,
        'actor_type' => $actorType,
    ];
}

    public function OldresolveActor($document)
    {
        $mapper = [
            "mission" => "actor_details",
            "purchase_request" => "actor_details",
            "taxi_paper" => "actor_details",
            "fee_note" => "actor_details",
            "absence_request" => "actor_details",
            "regularization_sheet" => "actor_details",
            "invoice_provider" => "actor_details",
        ];

        // throw new Exception(json_encode($document['actor_details']), 1);
        // throw new Exception(json_encode($document), 1);

        $slug = $document["document_type"]["relation_name"];

        return $document[$mapper[$slug]] ?? null;
    }

    
    public function resolveHeadStepRole($step, $document)
    {
        // $rule = $step->assignment_rule;
        $rule = $step["assignment_rule"];

        $actor = $this->resolveActor($document);


        // if ($actor) {
        //     $employeeId = $actor["id"] ?? null; // employee_id
        //     $actorId = $actor["id"];
        // }

        // throw new Exception(json_encode($actorId, JSON_PRETTY_PRINT), 1);

        switch ($rule) {
            /**
             * =====================================
             * CHEF DE SERVICE
             * =====================================
             */
            case "HEAD_OF_DEPARTMENT":

    $actorType = $document["actor_type"] ?? null;
    $actorId = $document["actor_id"] ?? null;

   

    if (!$actorId) {
        return null;
    }

    $baseUrl = config("services.department_service.base_url");

    /*
    |--------------------------------------------------------------------------
    | Résolution selon le type d'acteur
    |--------------------------------------------------------------------------
    */
    if ($actorType === "DEPARTMENT") {
        $url = $baseUrl . "/{$actorId}/head";
    } else {
        $url = $baseUrl . "/employee/{$actorId}/head-of-department";
    }

    $response = Http::acceptJson()->get($url);

    // 

    if ($response->ok()) {
        return $response->json();
    }

     throw new Exception(
        json_encode($response->body(), JSON_PRETTY_PRINT),
        1
    );

   
            /**
             * =====================================
             * SUPÉRIEUR HIÉRARCHIQUE
             * =====================================
             */
            case "DIRECT_MANAGER":

                  if ($actor) {
            $employeeId = $actor["id"] ?? null; // employee_id
            $actorId = $actor["id"];
        }
        
                if (!$employeeId) {
                    return null;
                }

                $response = Http::acceptJson() //withToken(request()->bearerToken())->
                    ->get(
                        config("services.department_service.base_url") .
                            "/employee/{$employeeId}/relationships",
                        [
                            "type" => "manager",
                        ]
                    );

                if ($response->ok()) {
                    $data = $response->json()["related_employee"]["employee"][
                        "user"
                    ];

                    if (!$data) {
                        throw new Exception(json_encode($response->json()), 1);
                    }

                    return $data;
                }

                throw new Exception(json_encode($response->body()), 1);

                return null;

            case "MISSION_EXECUTOR":
                $userData = $document["actor_details"];

                // throw new Exception(json_encode($document["mission"]['actor_details']), 1);

                return ["userData" => $userData];

            case "SIGNATORY":
                $response = Http::acceptJson()
                    ->withToken(request()->bearerToken())
                    ->get(
                        config("services.user_service.base_url") .
                            "/by-permissions",
                        [
                            "actions" => ["sign"],
                            "document_type_id" => $document["document_type_id"],
                        ]
                    );

                // throw new Exception(json_encode(["ok"]), 1);

                if ($response->ok()) {
                    return $response->json("data");
                }

                throw new Exception(
                    json_encode($response->body(), JSON_PRETTY_PRINT),
                    1
                );

            case "OPERATOR":
            case "PURCHASE_SERVICE":

        // throw new Exception(    json_encode($rule, JSON_PRETTY_PRINT), 1);


                $response = Http::acceptJson()
                    ->withToken(request()->bearerToken())
                    ->get(
                        config("services.department_service.base_url") .
                            "/by-responsibility",
                        [
                            "responsibility" => [$rule],
                        ]
                    );

                // throw new Exception(json_encode(["ok"]), 1);

                if ($response->ok()) {
                // throw new Exception(json_encode($response->json()), 1);

                    return $response->json("data");
                }
                // throw new Exception(json_encode(["ok"]), 1);


                throw new Exception(
                    json_encode($response->body(), JSON_PRETTY_PRINT),
                    1
                );

            default:
                return null;
        }
    }

    public function resolveUser(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        $response = Http::acceptJson()->get(
            config("services.user_service.base_url") . "/{$userId}"
        );

        if (!$response->ok()) {
            throw new Exception(json_encode('$response->body()'), 1);
            return null;
        }

        return $response->json()["user"];
    }

    public function resolveUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $response = Http::acceptJson()->post(
            config("services.user_service.base_url") . "/batch",
            [
                "user_ids" => array_values(array_unique($userIds)),
            ]
        );

        if (!$response->ok()) {
            return [];
        }

        return $response->json()["users"] ?? [];
    }

    /**
     * Résout les utilisateurs à partir d'une liste de rôles
     *
     * @param array $roleIds
     * @return array
     */
    public function resolveUsersByRoles(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $users = [];

        foreach ($roleIds as $roleId) {
            $response = Http::acceptJson()->get(
                config("services.user_service.base_url") . "/by-role/{$roleId}"
            );

            if (!$response->ok()) {
                continue;
            }

            $data = $response->json();

            
            

            /**
             * Structure attendue :
             * [
             *   "success" => true,
             *   "data" => [...]
             * ]
             */

            if (!isset($data["data"])) {
                continue;
            }

            $users[$roleId] = $data["data"];
        }

        return $users;
    }

    /**
     * Retourne les users groupés par rôle
     *
     * [
     *   role_id => [users]
     * ]
     */
    public function resolveUsersGroupedByRoles(array $roleIds): array
    {
        $users = $this->resolveUsersByRoles($roleIds);

        return collect($users)
            ->groupBy("role_id")
            ->toArray();
    }

    public function resolveExceptionalApprovers(
    int $employeeId,
    array $documentData
): array {
    return $this->effectiveResponsibilityService
        ->getEmployeesWithResponsibility(
            "APPROVAL_EXCEPTION",$employeeId
        );
}
public function formatExceptionalApprovers(
    array $exceptionalApprovers
): array {

    $assignments = [];
    $roleIds = [];

    foreach ($exceptionalApprovers as $approver) {

        $userId = $approver["user_id"] ?? null;

        $employeeId = $approver["employee_id"] ?? null;

        $roleId = data_get(
            $approver,
            "position.role_id"
        );

        if (!$userId || !$roleId) {
            continue;
        }

        $roleId = (int) $roleId;

        $assignments[] = [
            "user_id" => (int) $userId,

            "employee_id" => $employeeId
                ? (int) $employeeId
                : null,

            "role_id" => $roleId,
        ];

        $roleIds[] = $roleId;
    }

    return [
        "role_ids" => array_values(
            array_unique($roleIds)
        ),

        "assignments" => $assignments,
    ];
}
}
