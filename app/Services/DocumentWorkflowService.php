<?php

namespace App\Services;

use App\Models\Signature;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\Document\DocumentServiceClient;
use App\Services\DocumentEnricherRegistry;
use App\Services\EffectiveResponsibilityService;
use App\Services\Visibility\VisibilityPolicyResolver;
use App\Services\Workflow\WorkflowInstanceResolverService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocumentWorkflowService
{
    protected WorkflowInstanceResolverService $resolver;
    protected DocumentEnricherRegistry $registry;
    protected DocumentServiceClient $documentClient;
    protected WorkflowInstanceService $workflowInstanceService;
    protected EffectiveResponsibilityService $effectiveResponsibilityService;
    protected VisibilityPolicyResolver $visibilityPolicyResolver;
    protected DepartmentContextService $departmentContextService;
    

    const CONTEXT_VALIDATION = "TO_VALIDATE";
    const CONTEXT_MY_DOCUMENTS = "MY_DOCUMENTS";

    const FILTER_PENDING = "PENDING";
    const FILTER_IN_PROGRESS = "IN_PROGRESS";
    const FILTER_COMPLETE = "COMPLETE";
    const FILTER_REJECTED = "REJECTED";
    const FILTER_ALL_DOCUMENTS = "ALL_DOCUMENTS";

    public function __construct(
        WorkflowInstanceResolverService $workflowInstanceResolverService,
        DocumentEnricherRegistry $documentEnricherRegistry,
        DocumentServiceClient $documentClient,
        WorkflowInstanceService $workflowInstanceService,
        EffectiveResponsibilityService $effectiveResponsibilityService,
        VisibilityPolicyResolver $visibilityPolicyResolver,
        DepartmentContextService $departmentContextService
        
    ) {
        $this->resolver = $workflowInstanceResolverService;
        $this->registry = $documentEnricherRegistry;
        $this->documentClient = $documentClient;
        $this->workflowInstanceService = $workflowInstanceService;
        $this->effectiveResponsibilityService = $effectiveResponsibilityService;
        $this->visibilityPolicyResolver = $visibilityPolicyResolver;
        $this->departmentContextService = $departmentContextService;
    }

  public function getDocuments(
    array $params,
    Request $request,
    WorkflowPermissionService $permissionService
): array {

    $benchmark = [];
    $start = microtime(true);

    $mark = function ($name) use (&$benchmark, &$start) {
        $now = microtime(true);

        $benchmark[$name] = [
            'duration_ms' => round(($now - $start) * 1000, 2),
        ];

        $start = $now;
    };

    [
        "employeeId" => $employeeId,
        "userId" => $userId,
        "roleId" => $roleId,
        "document_type" => $document_type,
        "validationContext" => $validationContext,
        "filters" => $filters,
        "filterContext" => $filterContext,
        "currentPage" => $currentPage,
        "per_page" => $per_page,
        "isStat" => $isStat,
    ] = $params;


    /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */

    $baseQuery = $this->buildWorkflowQuery($validationContext);

    if (!empty($document_type)) {
        $baseQuery->where(
            "workflow_instances.document_type_relation_name",
            $document_type
        );
    }

    $mark("build_base_query");


    $currentEmployeeContext = $this->effectiveResponsibilityService
        ->getContext($employeeId);

    

    /*
    |--------------------------------------------------------------------------
    | Responsibilities
    |--------------------------------------------------------------------------
    */

    $responsibilities = $this->effectiveResponsibilityService
        ->getForEmployee($employeeId , $currentEmployeeContext);

    $mark("get_responsibilities");


    /*
    |--------------------------------------------------------------------------
    | Document IDs
    |--------------------------------------------------------------------------
    */

    $documentIdsNotPaginated = $this->getDocumentIds(
        $filterContext,
        clone $baseQuery,
        $roleId,
        $userId,
        $validationContext,
        $document_type[0],
        $employeeId,
        $responsibilities,
        $filters,
        !empty($filters["statut"]),
        !empty($filters["statut"]),
    );

    $mark("get_document_ids");


    $documentIds = collect($documentIdsNotPaginated)
        ->pluck("document_id");

    $mark("pluck_document_ids");


    /*
    |--------------------------------------------------------------------------
    | Document Service
    |--------------------------------------------------------------------------
    */

    $flatDocuments = collect(
        $this->documentClient->getDocumentTypesByIds(
            $documentIds->toArray()
        )
    )
        ->sortByDesc("id")
        ->values()
        ->all();

    $mark("get_documents_from_document_service");


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    $permissionsByDocType = $this->getPermissions(
        $flatDocuments,
        $userId,
        $roleId,
        $request,
        $permissionService
    );

    $mark("get_permissions");

    $actorIds = collect($flatDocuments)
    ->pluck('actor_id')
    ->filter()
    ->unique()
    ->values()
    ->toArray();



$currentDepartmentId = data_get(
    $currentEmployeeContext,
    'active_position.department_id'
);



$sameDepartmentMap = $this->getSameDepartmentMap(
    $actorIds,
    $currentDepartmentId
);

    // throw new Exception(json_encode($sameDepartmentMap), 1);

    

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    */
    $workflowInstances = WorkflowInstance::query()
    ->whereIn("document_id", $documentIds)
    ->get()
    ->keyBy("document_id");

$workflowInstanceIds = $workflowInstances
    ->pluck("id")
    ->filter()
    ->values()
    ->toArray();

$workflowSteps = WorkflowInstanceStep::query()
    ->whereIn("workflow_instance_id", $workflowInstanceIds)
    ->with([
        "workflowStep.workflowActionSteps.workflowAction"
    ])
    ->get()
    ->groupBy("workflow_instance_id");

    $filteredDocuments = collect($flatDocuments)
        ->filter(
            fn($doc) => $this->canView(
            $doc,
            $permissionsByDocType,
            $employeeId,
            $userId,
            $validationContext,
            $document_type,
            $responsibilities,
            $workflowInstances,
            $workflowSteps,
            $sameDepartmentMap
            )
        )
        ->values();

    $mark("can_view_filter");


      /*
    |--------------------------------------------------------------------------
    | Stat
    |--------------------------------------------------------------------------
    */

    if ($isStat) {

        $filteredDocumentIds = $filteredDocuments->pluck("id");

        $documentsCount = $this->fetchDocuments(
            $filteredDocumentIds,
            $document_type,
            $filters,
            $request,
            $isStat,
            false
        );

        $mark("fetch_documents_stat");

        logger()->info("GET DOCUMENTS BENCHMARK", [
            "employee_id" => $employeeId,
            "document_count" => $documentIds->count(),
            "filtered_count" => $filteredDocuments->count(),
            "benchmark" => $benchmark,
        ]);

        return [
            "count" =>$documentsCount// collect($documents)->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $page = max((int) $currentPage, 1);
    $perPage = max((int) $per_page, 1);

    $total = $filteredDocuments->count();

    $pagedDocuments = $filteredDocuments
        ->slice(($page - 1) * $perPage, $perPage)
        ->values();

    $mark("pagination");



    $filteredDocumentIds = $pagedDocuments->pluck("id");

    $mark("prepare_filtered_ids");


    /*
    |--------------------------------------------------------------------------
    | Fetch Documents
    |--------------------------------------------------------------------------
    */

    $documents = $this->fetchDocuments(
        $filteredDocumentIds,
        $document_type,
        $filters,
        $request
    );

    $mark("fetch_documents");


    $pagination = [
        "current_page" => $page,
        "per_page" => $perPage,
        "total" => $total,
        "last_page" => max(1, (int) ceil($total / $perPage)),
    ];


    if (collect($documents)->isEmpty()) {

        logger()->info("GET DOCUMENTS BENCHMARK", [
            "employee_id" => $employeeId,
            "document_count" => $documentIds->count(),
            "filtered_count" => $filteredDocuments->count(),
            "benchmark" => $benchmark,
        ]);

        return [
            "data" => [],
            "pagination" => $pagination,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Workflow Instances
    |--------------------------------------------------------------------------
    */

    $workflowInstances = WorkflowInstance::query()
        ->whereIn("document_id", $documentIds)
        ->get()
        ->keyBy("document_id");

    $mark("get_workflow_instances");


    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    $availabilityContexts = $this->availabilityContexts(
        $filteredDocumentIds->toArray()
    );

    $mark("availability_contexts");


    $contextsByDocId = collect($availabilityContexts)
        ->keyBy("document_id");

    $mark("key_by_contexts");


    /*
    |--------------------------------------------------------------------------
    | Enrichment
    |--------------------------------------------------------------------------
    */

    $documents = collect($documents)
        ->map(function ($doc) use (
            $contextsByDocId,
            $workflowInstances,
            $userId
        ) {

            $context = $contextsByDocId->get($doc["id"]);

            return $this->enrichDocument(
                $doc,
                $workflowInstances[$doc["id"]],
                $userId,
                $context
            );

        })
        ->values()
        ->toArray();

    $mark("enrich_document");


    /*
    |--------------------------------------------------------------------------
    | Actionable Steps
    |--------------------------------------------------------------------------
    */

    $actionableSteps = WorkflowInstanceStep::query()
        ->whereHas("assignments", function ($q) use ($roleId) {
            $q->where("role_id", $roleId)
                ->where("decision", "PENDING");
        })
        ->where("status", "PENDING")
        ->with("assignments")
        ->get()
        ->keyBy("workflow_instance_id");

    $mark("get_actionable_steps");


    /*
    |--------------------------------------------------------------------------
    | Final Enrichment
    |--------------------------------------------------------------------------
    */

    $documents = $this->enrichDocuments(
        $documents,
        $permissionsByDocType,
        $workflowInstances,
        $actionableSteps,
        $employeeId,
        $userId,
        $validationContext
    );

    $mark("enrich_documents_final");


    /*
    |--------------------------------------------------------------------------
    | Benchmark
    |--------------------------------------------------------------------------
    */

    $totalTime = array_sum(
        array_column($benchmark, "duration_ms")
    );

    logger()->info("GET DOCUMENTS BENCHMARK", [
        "employee_id" => $employeeId,
        "user_id" => $userId,
        "document_count" => $documentIds->count(),
        "filtered_count" => $filteredDocuments->count(),
        "returned_count" => count($documents),
        "total_ms" => round($totalTime, 2),
        "benchmark" => $benchmark,
    ]);


    return [
        "data" => $documents,
        "pagination" => $pagination,
    ];
}



protected function getSameDepartmentMap(
    array $employeeIds,
    int $currentDepartmentId
): array {

    $employeeIds = array_values(array_unique(
        array_filter($employeeIds)
    ));

    if (empty($employeeIds)) {
        return [];
    }

    $contexts = $this->departmentContextService
        ->getEmployeeContextMap($employeeIds);

    // throw new Exception(json_encode($contexts), 1);
    

    $result = [];

    foreach ($contexts as $employeeId => $context) {

        $departmentId = data_get(
            $context,
            'active_position.department_id'
        );

        $result[$employeeId] =
            $departmentId !== null &&
            (int) $departmentId === (int) $currentDepartmentId;
    }

    return $result;
}


    private function buildWorkflowQuery(string $validationContext)
    {
        // $query = WorkflowInstanceStep::query()->with("workflowInstance");

        $query = WorkflowInstanceStep::query()->join(
            "workflow_instances",
            "workflow_instance_steps.workflow_instance_id",
            "=",
            "workflow_instances.id"
        );

        if ($validationContext === self::CONTEXT_VALIDATION) {
        }

        if ($validationContext === self::CONTEXT_MY_DOCUMENTS) {
        }

        return $query;
    }

    private function enrichDocument(
        array $doc,
        WorkflowInstance $instance,
        $currentUserId,
        ?array $context
    ): array {
        if (!isset($doc["document_type_slug"])) {
            // throw new Exception(json_encode($doc), 1);
        }

        $resolver = $this->registry->resolve($doc["document_type"]["slug"]);

        $resolved = $resolver->enrich($doc, $context);

        $cancelable = $this->workflowInstanceService->cancelable($instance);

        $resolved["availability"]["can_cancel"] =
            $cancelable && $currentUserId == $doc["created_by"];

        return $resolved;
    }

    public function availabilityContexts(array $documentIds)
    {
        // 1. Récupérer tous les workflows en une fois
        $workflows = WorkflowInstance::query()
            ->whereIn("document_id", $documentIds)
            ->get()
            ->keyBy("document_id");

        // 2. Signatures en batch
        $signatures = Signature::query()
            ->whereIn("document_id", $documentIds)
            ->with("signatureType")
            ->get()
            ->groupBy("document_id");

        // 3. Steps en batch avec actions + permissions
        $workflowIds = $workflows->pluck("id")->toArray();

        $steps = WorkflowInstanceStep::query()
            ->whereIn("workflow_instance_id", $workflowIds)
            ->where("status", "COMPLETE")
            ->with(["workflowStep.workflowActionSteps.workflowAction"])
            ->get()
            ->groupBy("workflow_instance_id");

        // 4. Build response
        return collect($documentIds)
            ->map(function ($documentId) use ($workflows, $signatures, $steps) {
                $workflow = $workflows[$documentId] ?? null;

                if (!$workflow) {
                    return [
                        "document_id" => $documentId,
                        "workflow_status" => null,
                        "signatures" => [],
                        "completed_steps" => [],
                    ];
                }

                $docSignatures = ($signatures[$documentId] ?? collect())

                    ->map(function ($signature) {
                        return [
                            "code" => $signature->signatureType->code,
                            "signed" => true,
                            "signed_at" => $signature->signed_at,
                        ];
                    })
                    ->values();

                $docSteps = $this->formatCompletedSteps(
                    $steps[$workflow->id] ?? collect()
                );

                return [
                    "document_id" => $documentId,

                    "workflow_status" => $workflow->status,

                    "signatures" => $docSignatures,

                    "completed_steps" => $docSteps,
                ];
            })
            ->values()
            ->toArray();
    }

    public function availabilityContext(int $documentId)
    {
        $workflow = WorkflowInstance::where(
            "document_id",
            $documentId
        )->first();

        if (!$workflow) {
            return response()->json([
                "workflow_status" => null,
                "signatures" => [],
                "completed_steps" => [],
            ]);
        }

        $signatures = Signature::query()
            ->where("document_id", $documentId)
            ->with("signatureType")
            ->get()
            ->map(function ($signature) {
                return [
                    "code" => $signature->signatureType->code,
                    "signed" => true,
                    "signed_at" => $signature->signed_at,
                ];
            });

        $completedSteps = WorkflowInstanceStep::query()
            ->where("workflow_instance_id", $workflow->id)
            ->where("status", "COMPLETE")
            ->with(["workflowStep.workflowActionSteps.workflowAction"])
            ->get();

        $docSteps = $this->formatCompletedSteps($completedSteps ?? collect());

        return response()->json([
            "workflow_status" => $workflow->status,

            "signatures" => $signatures,

            "completed_steps" => $docSteps,
        ]);
    }

    private function getDocumentIds(
        string $filterContext,
        Builder $query,
        int $roleId,
        int $userId,
        string $validationContext,
        string $document_type,
        int $employeeId,
        array $responsibilities,
        array $filters = [],
        bool $applyStatusFilter = true,
        bool $applyRoleFilter = true
    ) {
        // $filterContext = $filters["statut"];
        $statut = $filters["statut"] ?? null;

        if ($validationContext === self::CONTEXT_MY_DOCUMENTS) {
            if ($filterContext === self::FILTER_PENDING) {
                // throw new Exception($filterContext, 1);

                /*
    |--------------------------------------------------------------------------
    | FILTRE ROLE (OPTIONNEL)
    |--------------------------------------------------------------------------
    */

                if ($applyRoleFilter) {
                    $query->whereHas("assignments", function ($q) use (
                        $roleId,
                        $statut
                    ) {
                        $q->where("role_id", $roleId)->where(
                            "decision",
                            "PENDING"
                            // $statut != "COMPLETE" ? $statut : "APPROVED"
                        );
                    });
                }

                /*
    |--------------------------------------------------------------------------
    | FILTRE STATUT
    |--------------------------------------------------------------------------
    */
                if ($applyStatusFilter && !empty($statut)) {
                    $query->where(function ($q) use ($roleId, $statut) {
                        $q->whereHas("assignments", function ($a) use (
                            $roleId,
                            $statut
                        ) {
                            $a->where("role_id", $roleId)->where(
                                "decision",
                                "PENDING"
                                // $statut != "COMPLETE" ? $statut : "APPROVED"
                            );
                        })->where("status", "PENDING");
                    });
                }
            }

            if ($filterContext === self::FILTER_IN_PROGRESS) {
                if ($applyStatusFilter && !empty($statut)) {
                    // throw new Exception($filterContext, 1);

                    $query->whereHas("workflowInstance", function ($q) use (
                        $statut
                    ) {
                        $q->where("status", "PENDING");
                    });
                }
            }

            if ($filterContext === self::FILTER_COMPLETE) {
                // throw new Exception($filterContext, 1);

                $query->whereHas("workflowInstance", function ($q) use (
                    $statut
                ) {
                    $q->where("status", $statut);
                });
            }
        }

        if ($validationContext === self::CONTEXT_VALIDATION) {
            // throw new Exception($validationContext, 1);


            $policy = $this->visibilityPolicyResolver->resolve(
    $document_type
);

$query = $policy->apply(
    $query,
    $roleId,
    $userId,
    $employeeId,
    $responsibilities
);



            if ($filterContext === self::FILTER_PENDING) {
                // if ($applyStatusFilter && !empty($statut)) {
                //     $query->whereHas("workflowInstance", function ($q) use (
                //         $statut
                //     ) {
                //         $q->where("status", "PENDING");
                //     });
                // }

                /*
    |--------------------------------------------------------------------------
    | FILTRE ROLE (OPTIONNEL)
    |--------------------------------------------------------------------------
    */

                if ($applyRoleFilter) {
                    // throw new Exception($applyRoleFilter, 1);

                    $query
                        ->where("workflow_instance_steps.status", "PENDING")
                        ->whereHas("assignments", function ($q) use (
                            $roleId,
                            $statut
                        ) {
                            $q->where("role_id", $roleId)->where(
                                "decision",
                                "PENDING"
                            );
                        });
                }

                /*
    |--------------------------------------------------------------------------
    | FILTRE STATUT
    |--------------------------------------------------------------------------
    */

                // if ($applyStatusFilter && !empty($statut)) {
                //     $query->where(function ($q) use ($roleId, $statut) {
                //         $q->whereHas("assignments", function ($a) use (
                //             $roleId,
                //             $statut
                //         ) {
                //             $a->where("role_id", $roleId)->where("decision","PENDING");
                //         })->where("status", $statut);
                //     });
                // }
            }

            if ($filterContext === self::FILTER_IN_PROGRESS) {
                if ($applyStatusFilter && !empty($statut)) {
                    // throw new Exception($filterContext, 1);

                    $query->whereHas("workflowInstance", function ($q) use (
                        $statut
                    ) {
                        $q->where("status", "PENDING");
                    });
                }
            }

            //COMPLETE

            if ($filterContext === self::FILTER_COMPLETE) {
                // throw new Exception($filterContext, 1);

                $query->whereHas("workflowInstance", function ($q) use (
                    $statut
                ) {
                    $q->where("status", $statut);
                });
            }

            if ($filterContext === self::FILTER_ALL_DOCUMENTS) {
            }
        }

        return $query
            ->select("workflow_instances.document_id")
            ->distinct()
            ->get();
        // ->paginate($count);

        // return $query
        //     ->get()
        //     ->pluck("workflowInstance.document_id")
        //     ->filter()
        //     ->unique()
        //     ->values();
    }

    private function getDocumentStats(
        array $documentTypes,
        string $context
    ): array {
        $query = WorkflowInstance::query();

        $query->whereIn("document_type_id", $documentTypes);

        return [
            "total" => (clone $query)->count(),

            "pending" => (clone $query)->where("status", "PENDING")->count(),

            "complete" => (clone $query)->where("status", "COMPLETE")->count(),

            "rejected" => (clone $query)->where("status", "REJECTED")->count(),
        ];
    }

    protected function fetchDocuments(
        $documentIds,
        array $documentTypes,
        ?array $filters,
        Request $request,
        bool $isStat=false,
        bool $shouldEnrich = true
    ): array {
        $response = Http::withToken($request->bearerToken())
            ->acceptJson()
            ->get(config("services.document_service.base_url") . "/by-ids", [
                "ids" => $documentIds->toArray(),
                "documentTypes" => $documentTypes,
                "filters" => $filters,
                "shouldEnrich" => $shouldEnrich,
                "isStat"=>$isStat
            ]);

        // throw new Exception(json_encode($response->body()), 1);
        // throw new Exception(json_encode($documentTypes), 1);

        if ($response->ok()) {
            return $response->json();
        } else {
            throw new Exception(json_encode($response->body()), 1);
        }

        // return $response->ok() ? $response->json() : [];
    }

    protected function getPermissions(
        array $documents,
        int $userId,
        int $roleId,
        Request $request,
        WorkflowPermissionService $workflowPermissionService
    ) {
        $data = [
            "user_id" => $userId,
            "role_id" => $roleId,
            "count" => count($documents),
            "documents" => $documents,
        ];

        $permissions = $workflowPermissionService->checkPermissions2(
            $data,
            $request
        );

        // app()->call(
        //     'App\Http\Controllers\WorkflowValidationController@checkPermissions2',
        //     ['data' => $data, 'request' => $request]
        // );

        return collect($permissions)->keyBy("documentId");
    }

    protected function enrichDocuments(
        array $documents,
        $permissionsByDocType,
        $workflowInstances,
        $actionableSteps,
        int $employeeId,
        int $userId,
        string $context
    ): array {
        $translations = $this->statusTranslations();

        // throw new Exception(json_encode($documents), 1);

        return collect($documents)
           
            ->map(function ($doc) use (
                $workflowInstances,
                $actionableSteps,
                $translations
            ) {
                $instance = $workflowInstances[$doc["id"]] ?? null;

                $doc["workflow_status"] = null;
                $doc["can_validate"] = false;

                $status_label_resolved =$this->resolver->resolveWorkflowStatusLabel($instance) ??
                    "N/D";

                // throw new Exception(json_encode($status_label_resolved), 1);

                if ($instance) {
                    $doc["workflow_status"] = $status_label_resolved;
                    $doc["can_validate"] = isset(
                        $actionableSteps[$instance->id]
                    );
                }

                return $doc;
            })
            ->values()
            ->toArray();
    }

    private function formatCompletedSteps($steps)
    {
        return $steps
            ->map(function ($step) {
                return [
                    "code" => $step->workflowStep->code,

                    "name" => $step->workflowStep->name,

                    "actions" => $step->workflowStep->workflowActionSteps
                        ->map(function ($actionStep) {
                            return [
                                "action" => [
                                    "id" => $actionStep->workflowAction->id,
                                    "name" => $actionStep->workflowAction->name,
                                    "label" =>
                                        $actionStep->workflowAction
                                            ->action_label,
                                ],

                                "permission_required" =>
                                    $actionStep->permission_required,

                                "message" => $actionStep->action_step_message,

                                "transaction_type_code" =>
                                    $actionStep->transaction_type_code,

                                "requirements" => $actionStep->requirements,

                                "visibility_requirements" =>
                                    $actionStep->visibility_requirements,
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();
    }

    private function getStepsByPermissions(
        array $completedSteps,
        array $permissions
    ): array {
        return collect($completedSteps)
            ->map(function ($instanceStep) use ($permissions) {
                $matchedActions = collect(
                    $instanceStep["workflow_step"]["workflow_action_steps"] ??
                        []
                )
                    ->filter(function ($actionStep) use ($permissions) {
                        return in_array(
                            $actionStep["permission_required"] ?? null,
                            $permissions
                        );
                    })
                    ->map(function ($actionStep) {
                        return [
                            "permission_required" =>
                                $actionStep["permission_required"],

                            "message" =>
                                $actionStep["action_step_message"] ?? null,

                            "transaction_type_code" =>
                                $actionStep["transaction_type_code"] ?? null,

                            "requirements" =>
                                $actionStep["requirements"] ?? null,

                            "visibility_requirements" =>
                                $actionStep->visibility_requirements ?? null,

                            "action" => [
                                "id" =>
                                    $actionStep["workflow_action"]["id"] ??
                                    null,
                                "name" =>
                                    $actionStep["workflow_action"]["name"] ??
                                    null,
                                "label" =>
                                    $actionStep["workflow_action"][
                                        "action_label"
                                    ] ?? null,
                            ],
                        ];
                    })
                    ->values();

                if ($matchedActions->isEmpty()) {
                    return null;
                }

                return [
                    "step_id" => $instanceStep["workflow_step_id"],

                    "instance_step_id" => $instanceStep["id"],

                    "name" => $instanceStep["workflow_step"]["name"] ?? null,

                    "status" => $instanceStep["status"],

                    "executed_at" => $instanceStep["executed_at"],

                    "actions" => $matchedActions,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }


    protected function canView(
    array $doc,
    object $permissionsByDocType,
    int $employeeId,
    int $userId,
    string $context,
    array $currentDocTypeSlug,
    array $responsibilities,
     $workflowInstances,
     $workflowSteps,
     $sameDepartmentMap
): bool {



    $perm = $permissionsByDocType[
    $doc["document_type_id"]
] ?? null;

if (!$perm) {
    return false;
}

    /*
    |--------------------------------------------------------------------------
    | Owner / Actor
    |--------------------------------------------------------------------------
    */

    $isOwner = $doc["created_by"] === $userId;

    $isActor =
        $doc["actor_type"] === "EMPLOYEE" &&
        $doc["actor_id"] === $employeeId;


    /*
    |--------------------------------------------------------------------------
    | MY DOCUMENTS
    |--------------------------------------------------------------------------
    */

    if ($context === "MY_DOCUMENTS") {
        return $isOwner || $isActor;
    }
    /*
    |--------------------------------------------------------------------------
    | Document type
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $doc["document_type"]["relation_name"],
            $currentDocTypeSlug
        )
    ) { 
        return false;
    }






    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    $workflowInstance = $workflowInstances->get($doc["id"]);

    if (!$workflowInstance) {
        return false;
    }

    $steps = $workflowSteps->get($workflowInstance->id, collect());


    // $completedSteps = $steps->where(
    //     "status",
    //     "COMPLETE"
    // );


    /*
    |--------------------------------------------------------------------------
    | Signatures
    |--------------------------------------------------------------------------
    */

    // $completedStepWithSignPermission =
    //     $this->getStepsByPermissions(
    //         $completedSteps->toArray(),
    //         ["sign"]
    //     );


    // $stepWithSignPermission =
    //     $this->getStepsByPermissions(
    //         $steps->toArray(),
    //         ["sign"]
    //     );


    // $hasSignStep = !empty($stepWithSignPermission);

    // $hasCompletedSignStep = !empty($completedStepWithSignPermission);

    $hasSignStep = false;
$hasCompletedSignStep = false;

foreach ($steps as $instanceStep) {

    $actionSteps =
        $instanceStep->workflowStep->workflowActionSteps ?? [];

    foreach ($actionSteps as $actionStep) {

        if (($actionStep->permission_required ?? null) !== "sign") {
            continue;
        }

        $hasSignStep = true;

        if ($instanceStep->status === "COMPLETE") {
            $hasCompletedSignStep = true;
        }

        if ($hasSignStep && $hasCompletedSignStep) {
            break 2;
        }
    }
}


    /*
    |--------------------------------------------------------------------------
    | Responsibilities
    |--------------------------------------------------------------------------
    */

    $isAccounting = in_array(
        "ACCOUNTING",
        $responsibilities
    );

    $isSignatory = in_array(
        "SIGNATORY",
        $responsibilities
    );


    /*
    |--------------------------------------------------------------------------
    | Accounting visibility rule
    |--------------------------------------------------------------------------
    */

    if (
        $hasSignStep &&
        !$hasCompletedSignStep &&
        $isAccounting &&
        !$isSignatory
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    // $perm = $permissionsByDocType[
    //     $doc["document_type_id"]
    // ] ?? null;

    // if (!$perm) {
    //     return false;
    // }

    $permissions = $perm["permissions"];


    // /*
    // |--------------------------------------------------------------------------
    // | Owner / Actor
    // |--------------------------------------------------------------------------
    // */

    // $isOwner = $doc["created_by"] === $userId;

    // $isActor =
    //     $doc["actor_type"] === "EMPLOYEE" &&
    //     $doc["actor_id"] === $employeeId;


    /*
    |--------------------------------------------------------------------------
    | Department
    |--------------------------------------------------------------------------
    */
    // $start = microtime(true);

    // $isSameDepartment = //true;
    
    // $this->checkSameDepartment(
    //     $doc["actor_id"],
    //     $employeeId
    // );
    $actorId = $doc["actor_id"] ?? null;

    $isSameDepartment = $actorId !== null
        ? ($sameDepartmentMap[$actorId] ?? false)
        : false;

    // $duration = (microtime(true) - $start) * 1000;

    // if ($duration > 10) {
    //     logger()->info("SLOW checkSameDepartment", [
    //         "document_id" => $doc["id"],
    //         "actor_id" => $doc["actor_id"],
    //         "employee_id" => $employeeId,
    //         "duration_ms" => round($duration, 2),
    //     ]);
    // }


    // /*
    // |--------------------------------------------------------------------------
    // | MY DOCUMENTS
    // |--------------------------------------------------------------------------
    // */

    // if ($context === "MY_DOCUMENTS") {
    //     return $isOwner || $isActor;
    // }


    /*
    |--------------------------------------------------------------------------
    | TO VALIDATE / IN PROGRESS / COMPLETE
    |--------------------------------------------------------------------------
    */

    if (
        $context === "TO_VALIDATE" ||
        $context === "IN_PROGRESS" ||
        $context === "COMPLETE"
    ) {
        return
            ($permissions["view_department"] && $isSameDepartment)
            ||
            $permissions["view_all"];
    }


    /*
    |--------------------------------------------------------------------------
    | ALL DOCUMENTS
    |--------------------------------------------------------------------------
    */

    if ($context === "ALL_DOCUMENTS") {
        return
            $permissions["view_all"]
            ||
            (
                $permissions["view_department"]
                && $isSameDepartment
            )
            ||
            (
                $permissions["view_own"]
                && $isOwner
            );
    }


    return false;
}

    protected function OldcaneView(
        array $doc,
        object $permissionsByDocType,
        int $employeeId,
        int $userId,
        string $context,
        array $currentDocTypeSlug,
        array $responsibilities
    ): bool {
        if (
            !in_array(
                $doc["document_type"]["relation_name"],
                $currentDocTypeSlug
            )
        ) {
            return false;
        }

        $workflowInstance = WorkflowInstance::where(
            "document_id",
            $doc["id"]
        )->first();

        $completedSteps = WorkflowInstanceStep::query()
            ->where("workflow_instance_id", $workflowInstance->id)
            ->where("status", "COMPLETE")
            ->with(["workflowStep.workflowActionSteps.workflowAction"])
            ->get()
            ->toArray();

        $steps = WorkflowInstanceStep::query()
            ->where("workflow_instance_id", $workflowInstance->id)
            // ->where("status", "COMPLETE")
            ->with(["workflowStep.workflowActionSteps.workflowAction"])
            ->get()
            ->toArray();

        $completedStepWithSignPermission = $this->getStepsByPermissions(
            $completedSteps,
            ["sign"]
        );

        $stepWithSignPermission = $this->getStepsByPermissions($steps, [
            "sign",
        ]);

        $user = request()->get("user");

       
        

        $hasSignStep = !empty($stepWithSignPermission);

        $hasCompletedSignStep = !empty($completedStepWithSignPermission);

        $isAccounting = in_array("ACCOUNTING", $responsibilities);

        $isSignatory = in_array("SIGNATORY", $responsibilities);


        // throw new Exception(json_encode($responsibilities), 1);

      
        

        if (
    $hasSignStep &&
    !$hasCompletedSignStep &&
    $isAccounting &&
    !$isSignatory
) {
    return false;
}

        // throw new Exception(json_encode($doc["document_type"]["relation_name"]), 1);

        $perm = $permissionsByDocType[$doc["document_type_id"]] ?? null;

        if (!$perm) {
            return false;
        }

        $permissions = $perm["permissions"];

        $isOwner = $doc["created_by"] === $userId;

        $isActor =
            $doc["actor_type"] == "EMPLOYEE" && $doc["actor_id"] == $employeeId;

        // isset($doc["actor_type"]) && isset($doc["actor_id"])
        //     ? $doc["beneficiary"]["id"] === $userId
        //     : $doc["actor"]["id"] === $userId;

        if ($doc["actor_id"] == 0 || $doc["actor_id"] == null) {
            // throw new Exception(json_encode($doc["actor_id"]), 1);
            // return false;
        }

        // throw new Exception(json_encode($context), 1);

        $isSameDepartment = $this->checkSameDepartment(
            $doc["actor_id"], //employee_id
            $employeeId
        );

        /**
         * =========================
         * 📁 MES DOCUMENTS
         * =========================
         */
        if ($context === "MY_DOCUMENTS") {
            return $isOwner || $isActor;
        }

        /**
         * =========================
         * 🧾 À VALIDER
         * =========================
         */
        if (
            $context === "TO_VALIDATE" ||
            $context === "IN_PROGRESS" ||
            $context === "COMPLETE"
        ) {
            return ($permissions["view_department"] && $isSameDepartment) ||
                $permissions["view_all"];
        }

        /**
         * =========================
         * 🌍 ALL DOCUMENTS
         * =========================
         */
        if ($context === "ALL_DOCUMENTS") {
            return $permissions["view_all"] ||
                ($permissions["view_department"] && $isSameDepartment) ||
                ($permissions["view_own"] && $isOwner);
        }

        return false;
    }

    protected function checkSameDepartment(
        ?int $employee1,
        ?int $employee2
    ): bool {
        if (
            empty($employee1) ||
            empty($employee2) ||
            $employee1 == 0 ||
            $employee2 == 0
        ) {
            return false;
        }

        $response = Http::acceptJson()->get(
            config("services.department_service.base_url") .
                "/employees/same-department",
            [
                "employee1_id" => $employee1,
                "employee2_id" => $employee2,
            ]
        );

        if (!$response->successful()) {
            throw new Exception(
                json_encode([
                    "body" => $response->body(),
                    "employee1_id" => $employee1,
                    "employee2_id" => $employee2,
                ]),
                1
            );
            return false;
        }

        return (bool) data_get($response->json(), "same_department", false);
    }

    protected function statusTranslations(): array
    {
        return [
            "NOT_STARTED" => [
                "label" => "Validation non démarrée",
                "emoji" => "⏳",
                "color" => "info",
            ],
            "PENDING" => [
                "label" => "En cours de validation",
                "emoji" => "🟡",
                "color" => "warning",
            ],
            "COMPLETE" => [
                "label" => "Validation terminée",
                "emoji" => "✅",
                "color" => "success",
            ],
            "REJECT" => [
                "label" => "Rejetée",
                "emoji" => "❌",
                "color" => "error",
            ],
        ];
    }
}
