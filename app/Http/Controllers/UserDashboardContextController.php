<?php

namespace App\Http\Controllers;

use App\Models\DocumentTypeWorkflow;
use App\Models\Signature;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStep;
use App\Services\HttpClientService;
use Exception;
use Illuminate\Http\Request;

class UserDashboardContextController extends Controller
{


    /**
     * Règles qui correspondent à une action
     * que l'utilisateur doit effectuer lui-même.
     *
     * À adapter à tes règles métier.
     */
    private array $myActionRules = [
        "OWNER",
        "REQUESTER",
        "BENEFICIARY",
        "MISSION_OWNER",
        "MISSION_EXECUTOR",
    ];


    /**
     * Retourne le contexte du dashboard utilisateur.
     */
    public function show(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | CONTEXTE UTILISATEUR
        |--------------------------------------------------------------------------
        */

        $userId =
            $request->get("user")["id"];

        $actor_type =
            $request->get("actor_type");

        $actor_id =
            $request->get("actor_id");

        $roles =
            $request->input("roles", []);

        $departmentId =
            $request->input("department_id");


        /*
        |--------------------------------------------------------------------------
        | 1. RÉCUPÉRER LES TÂCHES PENDING
        |--------------------------------------------------------------------------
        */

        $tasks =
            WorkflowInstanceStep::query()

                ->whereHas(
                    "assignments",
                    function ($q) use (
                        $roles,
                        $userId
                    ) {

                        $q->where(
                            function ($sub) use (
                                $roles,
                                $userId
                            ) {

                                $sub
                                    ->where(
                                        "user_id",
                                        $userId
                                    )
                                    ->orWhereIn(
                                        "role_id",
                                        $roles
                                    );

                            }
                        );

                    }
                )

                ->where(
                    "status",
                    "PENDING"
                )

                ->with([
                    "workflowStep.workflow",
                    "workflowStep",
                ])

                ->get();


        /*
        |--------------------------------------------------------------------------
        | 2. SÉPARER VALIDATION / ACTION PERSONNELLE
        |--------------------------------------------------------------------------
        */

        $validationTasks =
            $tasks->filter(
                function ($step) {

                    $rule =
                        $step
                            ->workflowStep
                            ->assignment_rule;

                    /*
                     * Tout ce qui n'est pas une règle
                     * personnelle est considéré comme
                     * une tâche de validation.
                     */

                    return !in_array(
                        $rule,
                        $this->myActionRules
                    );

                }
            );


        $myPendingTasks =
            $tasks->filter(
                function ($step) {

                    $rule =
                        $step
                            ->workflowStep
                            ->assignment_rule;

                    return in_array(
                        $rule,
                        $this->myActionRules
                    );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | 3. WORKFLOW IDS
        |--------------------------------------------------------------------------
        */

        $workflowIds =
            $tasks
                ->pluck(
                    "workflowStep.workflow_id"
                )
                ->unique()
                ->values()
                ->all();


        /*
        |--------------------------------------------------------------------------
        | Aucun workflow
        |--------------------------------------------------------------------------
        */

        if (empty($workflowIds)) {

            $signatures =
                Signature::query()

                    ->whereActorType(
                        $actor_type
                    )

                    ->whereActorId(
                        $actor_id
                    )

                    ->with(
                        "signatureType"
                    )

                    ->get()

                    ->map(
                        function ($s) {

                            return [
                                "code" =>
                                    optional(
                                        $s->signatureType
                                    )->code,

                                "signed" =>
                                    true,

                                "signed_at" =>
                                    $s->signed_at,
                            ];

                        }
                    );

            return response()->json([

                "user_id" =>
                    $userId,

                "validation_tasks" =>
                    [],

                "my_pending_tasks" =>
                    [],

                "has_pending_validation_tasks" =>
                    false,

                "has_my_pending_tasks" =>
                    false,

                "signatures" =>
                    $signatures,

            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 4. MAPPING WORKFLOW -> DOCUMENT TYPE
        |--------------------------------------------------------------------------
        */

        $mapping =
            DocumentTypeWorkflow::query()

                ->whereIn(
                    "workflow_id",
                    $workflowIds
                )

                ->get()

                ->groupBy(
                    "workflow_id"
                );


        /*
        |--------------------------------------------------------------------------
        | 5. DOCUMENT TYPE IDS
        |--------------------------------------------------------------------------
        */

        $documentTypeIds =
            $mapping

                ->flatten()

                ->pluck(
                    "document_type_id"
                )

                ->unique()

                ->values()

                ->all();


        /*
        |--------------------------------------------------------------------------
        | 6. RÉCUPÉRATION DES DOCUMENT TYPES
        |--------------------------------------------------------------------------
        */

        $client =
            HttpClientService::service(
                "document"
            );


        $response =
            $client->get(
                "documentTypes",
                [
                    "ids" =>
                        $documentTypeIds
                ]
            );


        $documentTypes =
            $response["data"]["data"]
            ?? [];


        $documentTypeMap =
            collect(
                $documentTypes
            )
                ->keyBy("id");


        /*
        |--------------------------------------------------------------------------
        | 7. FORMATAGE DES TÂCHES DE VALIDATION
        |--------------------------------------------------------------------------
        */

        $validationDashboardTasks =
            $this->formatDashboardTasks(
                $validationTasks,
                $mapping,
                $documentTypeMap
            );


        /*
        |--------------------------------------------------------------------------
        | 8. FORMATAGE DES TÂCHES PERSONNELLES
        |--------------------------------------------------------------------------
        */

        $myPendingDashboardTasks =
            $this->formatDashboardTasks(
                $myPendingTasks,
                $mapping,
                $documentTypeMap
            );


        /*
        |--------------------------------------------------------------------------
        | 9. SIGNATURES
        |--------------------------------------------------------------------------
        */

        $signatures =
            Signature::query()

                ->whereActorType(
                    $actor_type
                )

                ->whereActorId(
                    $actor_id
                )

                ->with(
                    "signatureType"
                )

                ->get()

                ->map(
                    function ($s) {

                        return [

                            "code" =>
                                optional(
                                    $s->signatureType
                                )->code,

                            "signed" =>
                                true,

                            "signed_at" =>
                                $s->signed_at,

                        ];

                    }
                );


        /*
        |--------------------------------------------------------------------------
        | 10. RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            "user_id" =>
                $userId,


            /*
             * Documents que l'utilisateur
             * doit valider.
             */

            "validation_tasks" =>
                $validationDashboardTasks,


            /*
             * Documents pour lesquels
             * l'utilisateur doit agir.
             */

            "my_pending_tasks" =>
                $myPendingDashboardTasks,


            /*
             * Flags pratiques côté frontend.
             */

            "has_pending_validation_tasks" =>
                $validationDashboardTasks
                    ->isNotEmpty(),


            "has_my_pending_tasks" =>
                $myPendingDashboardTasks
                    ->isNotEmpty(),


            /*
             * Signatures de l'utilisateur.
             */

            "signatures" =>
                $signatures,

        ]);
    }


    /**
     * Formate les tâches pour le dashboard.
     */
    private function formatDashboardTasks(
        $tasks,
        $mapping,
        $documentTypeMap
    ) {

        return $tasks

            ->groupBy(
                function ($step) use (
                    $mapping
                ) {

                    $workflowId =
                        $step
                            ->workflowStep
                            ->workflow_id;


                    return
                        optional(
                            $mapping[$workflowId][0]
                            ?? null
                        )->document_type_id;

                }
            )

            ->map(
                function (
                    $steps,
                    $typeId
                ) use (
                    $documentTypeMap
                ) {

                    $docType =
                        $documentTypeMap->get(
                            $typeId
                        );


                    /*
                     * Document type introuvable.
                     */

                    if (!$docType) {

                        return null;

                    }


                    return [

                        "document_type" =>
                            $docType,

                        "count" =>
                            $steps->count(),

                    ];

                }
            )

            ->filter()

            ->values();
    }

    public function Oldshow(Request $request )
    {
        $userId = $request->get("user")["id"];

        $actor_type = $request->get("actor_type");

        $actor_id = $request->get("actor_id");

        $roles = $request->input("roles", []);

        $departmentId = $request->input("department_id");

        // 1. Récupérer workflows assignés à ces rôles
        // $tasks = WorkflowInstanceStep::query()
        //     ->whereHas("assignments", function ($q) use ($roles, $userId) {
        //         $q->where("user_id", $userId)->orWhereIn("role_id", $roles);
        //         $q->where("source_type", "!=" , "OWNER");
        //     })
        //     ->where("status", "PENDING")
        //     ->with(["workflowStep.workflow"])
        //     ->get();

      $excludedRules = [
    // "MISSION_EXECUTOR",
    // "MISSION_OWNER",
    // "REQUESTER",
    // "BENEFICIARY",
];


;

$tasks = WorkflowInstanceStep::query()
    ->whereHas("assignments", function ($q) use ($roles, $userId) {
        $q->where(function ($sub) use ($roles, $userId) {
            $sub->where("user_id", $userId)
                ->orWhereIn("role_id", $roles);
        })
        // ->where("source_type", "!=", "OWNER");
        ;
    })
    ->where("status", "PENDING")
    ->with(["workflowStep.workflow", "workflowStep"])
    ->get();

    // throw new Exception(json_encode($tasks), 1);

    $isValidatorUser = !$tasks->contains(function ($step) use ($excludedRules) {

    $rule = $step->workflowStep->assignment_rule;

    return in_array($rule, $excludedRules);
});

    // throw new Exception(json_encode($workflowContext), 1);
    // throw new Exception(json_encode($isValidatorUser), 1);


       $workflowIds = $tasks
    ->pluck("workflowStep.workflow_id")
    ->unique()
    ->values()
    ->all();

$mapping = DocumentTypeWorkflow::query()
    ->whereIn("workflow_id", $workflowIds)
    ->get()
    ->groupBy("workflow_id");

    $documentTypeIds = $mapping
    ->flatten()
    ->pluck("document_type_id")
    ->unique()
    ->values()
    ->all();

    $client = HttpClientService::service('document');

    $response = $client->get("documentTypes", ["ids" => $documentTypeIds]);

    

    
    $documentTypes = $response['data']['data'] ?? [];

    $documentTypeMap = collect($documentTypes)
    ->keyBy('id');
    
    // throw new Exception(json_encode($documentTypes), 1);

       $tasksByType = $tasks->groupBy(function ($step) use ($mapping, $documentTypeMap) {

    $workflowId = $step->workflowStep->workflow_id;

    $docTypeId = $mapping[$workflowId][0]->document_type_id ?? null;

    return $docTypeId;
});

    // throw new Exception(json_encode($tasksByType), 1);


       $tasks = $tasksByType
    ->map(function ($steps, $typeId) use ($documentTypeMap, $isValidatorUser) {

        $docType = $documentTypeMap->get($typeId);

        return [
            "document_type" => $docType,
            "view_url" => $isValidatorUser ? $docType['view_route'] : $docType['view_own_route']?? "my_documents",
            // [
            //     "id" => $typeId,
            //     "name" => $docType['name'] ?? null,
            //     "code" => $docType['code'] ?? null,
            //     "icon" => $docType['icon'] ?? null,
            //     "color" => $docType['color'] ?? null,
            // ],
            "count" => $steps->count(),
            "can_validate" => $isValidatorUser,
        ];
    })
    ->values();

    // throw new Exception(json_encode($tasks), 1);


        // 2. Signatures (employee-based)
        $signatures = Signature::query()
            // ->where("employee_id", $request->input("employee_id"))
            ->whereActorType($actor_type)
            ->whereActorId($actor_id)
            ->with("signatureType")
            ->get()
            ->map(
                fn($s) => [
                    "code" => $s->signatureType->code,
                    "signed" => true,
                    "signed_at" => $s->signed_at,
                ]
            );

        // 3. Availability globale
        return response()->json([
            "user_id" => $userId,
            "tasks" => $tasks,
            "signatures" => $signatures,
            "has_pending_tasks" => $tasks->isNotEmpty(),
            "isValidatorUser" => $isValidatorUser
        ]);
    }
}
