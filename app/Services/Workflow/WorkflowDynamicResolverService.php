<?php 

namespace App\Services\Workflow;

use Exception;
use Illuminate\Support\Facades\Http;


class WorkflowDynamicResolverService
{

  public function resolveActor( $document)
        {



        $mapper = [
                    "mission"=>"actor_details",
                    "purchase_request"=>"actor_details",
                    // "mission"=>"actor_id",
                ];

                $slug = $document["document_type"]["relation_name"];

        return $document [$slug][$mapper[$slug]];

                

                return $mapper[$documentTypeName];

                

        }
    public function resolveHeadStepRole( $step , $document)
        {
            
            // $rule = $step->assignment_rule;
            $rule = $step["assignment_rule"];

            $actor = $this->resolveActor($document); 
            $actorId = $actor["id"]; 
            $employeeId = $actor["employee"]["id"]; 


                    // throw new Exception(json_encode($actorId, JSON_PRETTY_PRINT), 1);



            switch ($rule) {

                /**
                 * =====================================
                 * CHEF DE SERVICE
                 * =====================================
                 */
                case 'HEAD_OF_DEPARTMENT':


                    if (!$actorId) {
                        return null;
                    }

                    $response = Http::get(
                        config("services.department_service.base_url")
                        . "/user/{$actorId}/head-of-department"
                    );

                    if ($response->ok()) {
                        return $response->json();
                    }

                    throw new Exception(json_encode($response->body(), JSON_PRETTY_PRINT), 1);


                    return null;

                /**
                 * =====================================
                 * SUPÉRIEUR HIÉRARCHIQUE
                 * =====================================
                 */
                case 'DIRECT_MANAGER':

                
                    if (!$employeeId) {
                        return null ;
                    }

                    $response = Http::acceptJson()-> //withToken(request()->bearerToken())->
    get(config('services.department_service.base_url') . "/employee/{$employeeId}/relationships", [
        'type' => 'manager'
    ]);

                    // $response = Http::get(
                    //     config("services.user_service.base_url")
                    //     . "/{$actorId}/manager"
                    // );

                        // throw new Exception(config('services.department_service.base_url') . "/employee/{$employeeId}/relationships", 1);
                        // throw new Exception(json_encode($response->body()), 1);


                    if ($response->ok()) {
                         $data =  $response->json()["related_employee"]["employee"]["user"];

                        // throw new Exception(json_encode($data), 1);

                       return $data;
                    }

                        throw new Exception(json_encode($response->body()), 1);


                    return null;

                 case 'MISSION_EXECUTOR':

                    $userData = $document["mission"]['actor_details'];

                        // throw new Exception(json_encode($document["mission"]['actor_details']), 1);


                    return ["userData" => $userData] ;

                default:
                    return null;
            }
        }



    public function resolveUser(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        $response = Http::get(
            config('services.user_service.base_url') . "/{$userId}"
        );

        if (!$response->ok()) {
            return null;
        }

        return $response->json()['user'];
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

        $response = Http::get(
            config('services.user_service.base_url') . "/by-role/{$roleId}"
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

        if (!isset($data['data'])) {
            continue;
        }

        $users[$roleId] = $data['data'];
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
            ->groupBy('role_id')
            ->toArray();
    }

}