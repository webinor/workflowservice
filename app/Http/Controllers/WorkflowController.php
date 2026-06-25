<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use Illuminate\Support\Str;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepRole;
use App\Models\WorkflowCondition;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use App\Models\DocumentTypeWorkflow;
use App\Models\WorkflowInstanceStep;
use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Models\Signature;
use App\Models\WorkflowActionStep;
use App\Models\WorkflowActionStepEvent;
use App\Models\WorkflowEventAudience;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStatusLabel;
use App\Models\WorkflowStepAttachmentType;
use App\Services\DocumentWorkflowService;
use App\Services\Workflow\WorkflowInstanceResolverService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\isEmpty;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Retourne les workflows actifs avec leurs étapes et transitions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $token = $request->bearerToken();

            $workflows = Workflow::whereActive(1)
                ->with([
                    "steps.stepRoles",
                    "steps.attachmentTypes",
                    "transitions.conditions",
                    "transitions.fromStep",
                    "transitions.toStep",
                    "documentTypeWorkflow",
                ])
                ->get();

            $mapped = $workflows->map(function ($workflow) use ($token) {
                $attachmentTypesData = collect([]);

                $attachmentTypeIds = $workflow->steps
                    ->flatMap(
                        fn($step) => $step->attachmentTypes->pluck(
                            "attachment_type_id"
                        )
                    )
                    ->unique()
                    ->values()
                    ->all();

                if (sizeof($attachmentTypeIds) > 0) {
                    # code...

                    // throw new Exception($attachmentTypeIds, 1);

                    $response = Http::acceptJson()
                        ->withHeaders([
                            "Authorization" => "Bearer $token",
                        ])
                        ->get(
                            config("services.document_service.base_url") .
                                "/get-attachment-types",
                            [
                                "ids" => implode(",", $attachmentTypeIds),
                            ]
                        );

                    // throw new Exception($attachmentTypeIds, 1);
                    // throw new Exception($response->body(), 1);

                    $attachmentTypesData = collect(
                        $response->json()["data"]
                    )->keyBy("id");

                    // throw new Exception($attachmentTypesData, 1);
                }
                return [
                    "id" => $workflow->id,
                    "name" => $workflow->name,
                    "code" => $workflow->code,

                    "steps" => $workflow->steps->map(function ($step) use (
                        $attachmentTypesData
                    ) {
                        return [
                            "id" => (string) $step->id,
                            "stepName" => $step->name,
                            "position" => $step->position,
                            "stepStatus" => $step->workflow_status_label_id,
                            "assignationMode" => $step->assignment_mode,
                            "assignmentRule" => $step->assignment_rule,
                            "is_payment_step" => $step->is_payment_step
                                ? "1"
                                : "0",
                            "is_archived_step" => $step->is_archived_step
                                ? "1"
                                : "0",

                            "roleId" => $step->stepRoles->map(function ($role) {
                                return $role->role_id;
                                // [
                                //     'id' => $role->id,
                                //     'role_id' => $role->role_id
                                // ];
                            }),

                            //  'attachmentTypeCategoryRequiredIdo' => $step->attachmentTypes ? [
                            //      'id' => ($step->attachmentTypes->first()),
                            //     // 'attachment_type_id' => $step->attachmentTypes->first()->attachment_type_id,
                            // ] : null,

                            "attachmentTypeCategoryRequiredId" => $step->attachmentTypes->map(
                                function ($attachmentType) use (
                                    $attachmentTypesData
                                ) {
                                    // Récupère les infos du microservice
                                    $docType =
                                        $attachmentTypesData[
                                            $attachmentType->attachment_type_id
                                        ] ?? null;

                                    return [
                                        "id" =>
                                            $attachmentType->attachment_type_id,
                                        "name" => $docType["name"] ?? null,
                                        "slug" => $docType["slug"] ?? null,
                                        "category_id" =>
                                            $docType[
                                                "attachment_type_category_id"
                                            ] ?? null,
                                        "category" =>
                                            Str::lower(
                                                $docType[
                                                    "attachment_type_category"
                                                ]["name"] . "-attachment"
                                            ) ?? "",
                                    ];
                                }
                            ),
                        ];
                    }),

                    "transitions" => $workflow->transitions->map(function (
                        $transition
                    ) {
                        return [
                            "id" => (string) $transition->id,
                            "fromStep" => (string) $transition->from_step_id,
                            "toStep" => (string) $transition->to_step_id,
                            "name" => $transition->name,
                            "conditionType" => Str::upper($transition->type),

                            //             'blockingRuleGroups' => $transition->conditions
                            // ->where('condition_kind', 'BLOCKING')
                            // ->map(function ($condition) {
                            //     return [
                            //         'id' => $condition->id,
                            //         'type' => $condition->condition_type, // exists / comparison
                            //         'existsTarget' => $condition->required_type,
                            //         'value' => collect($condition->required_id)
                            //         ->map(fn($v) => (string) $v),
                            //         'operator' => $condition->operator,
                            //         'field' => $condition->field ?? null,
                            //     ];
                            // })->values(),

                            "blockingRuleGroups" => $transition->conditions
                                ->where("condition_kind", "BLOCKING")
                                ->map(function ($condition) {
                                    // fallback group_id null
                                    $condition->group_id =
                                        $condition->group_id ?? "default";
                                    return $condition;
                                })
                                ->groupBy("group_id")
                                ->map(function ($conditions, $groupId) {
                                    return [
                                        "id" => $groupId,
                                        "rules" => $conditions
                                            ->map(function ($condition) {
                                                return [
                                                    "id" => $condition->id,
                                                    "type" =>
                                                        $condition->condition_type,
                                                    "existsTarget" =>
                                                        $condition->required_type,
                                                    "value" => is_array(
                                                        $condition->required_id
                                                    )
                                                        ? collect(
                                                            $condition->required_id
                                                        )->map(
                                                            fn(
                                                                $v
                                                            ) => (string) $v
                                                        )
                                                        : $condition->value,
                                                    "operator" =>
                                                        $condition->operator,
                                                    "field" =>
                                                        $condition->field ??
                                                        null,
                                                ];
                                            })
                                            ->values(),
                                    ];
                                })
                                ->values(),

                            // 🔵 Path Rules
                            // 'pathRuleGroups' => $transition->conditions
                            //     ->where('condition_kind', 'PATH')
                            //     ->map(function ($condition) {
                            //         return [
                            //             'id' => $condition->id,
                            //             'type' => $condition->condition_type, // comparison
                            //             'field' => $condition->field,
                            //             'operator' => $condition->operator,
                            //             'value' => $condition->condition_type == "comparison" ? floatval($condition->value) : $condition->value ,
                            //             'nextStep' => $condition->next_step_id,
                            //         ];
                            //     })->values(),

                            "pathRuleGroups" => $transition->conditions
                                ->where("condition_kind", "PATH")
                                ->map(function ($condition) {
                                    $condition->group_id =
                                        $condition->group_id ?? "default";
                                    return $condition;
                                })
                                ->groupBy("group_id")
                                ->map(function ($conditions, $groupId) {
                                    return [
                                        "id" => $groupId,
                                        "rules" => $conditions
                                            ->map(function ($condition) {
                                                return [
                                                    "id" => $condition->id,
                                                    "type" =>
                                                        $condition->condition_type,
                                                    "field" =>
                                                        $condition->field,
                                                    "operator" =>
                                                        $condition->operator,
                                                    "value" =>
                                                        $condition->condition_type ===
                                                        "comparison"
                                                            ? floatval(
                                                                $condition->value
                                                            )
                                                            : $condition->value,
                                                    "nextStep" =>
                                                        $condition->next_step_id,
                                                ];
                                            })
                                            ->values(),
                                    ];
                                })
                                ->values(),
                        ];
                    }),

                    "document_type" => $workflow->documentTypeWorkflow
                        ? [
                            "id" => $workflow->documentTypeWorkflow->id,
                            "document_type_id" =>
                                $workflow->documentTypeWorkflow
                                    ->document_type_id,
                        ]
                        : null,

                    "created_at" => $workflow->created_at
                        ? $workflow->created_at->format("Y-m-d H:i:s")
                        : "",
                ];
            });

            return response()->json([
                "success" => true,
                "data" => $mapped,
            ]);
        } catch (\Throwable $th) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Erreur lors de la récupération des workflows",
                    "error" => $th->getMessage(),
                ],
                500
            );
        }
    }

    public function getAvailabilityContext(
        DocumentWorkflowService $documentWorkflowService,
        int $documentId
    ) {
        return $documentWorkflowService->availabilityContext($documentId);
    }

    public function status(
        WorkflowInstanceResolverService $resolver,
        $documentId
    ) {
        Log::info("[WORKFLOW:STATUS] Start", [
            "document_id" => $documentId,
        ]);

        $instance = WorkflowInstance::where(
            "document_id",
            $documentId
        )->first();

        if (!$instance) {
            Log::warning("[WORKFLOW:STATUS] Workflow instance not found", [
                "document_id" => $documentId,
            ]);

            return [
                "status" => null,
                "step" => null,
                "transaction_types" => [],
            ];
        }

        Log::info("[WORKFLOW:STATUS] Instance found", [
            "instance_id" => $instance->id,
            "status" => $instance->status,
        ]);

        $currentInstanceStep = $resolver->getCurrentStep($instance);

        if (!$currentInstanceStep) {
            Log::warning("[WORKFLOW:STATUS] No current step found", [
                "instance_id" => $instance->id,
            ]);

            return [
                "status" => $instance->status,
                "step" => null,
                "transaction_types" => [],
            ];
        }

        $step = $currentInstanceStep->workflowStep;

        Log::info("[WORKFLOW:STATUS] Current step resolved", [
            "instance_id" => $instance->id,
            "step_id" => $step->id ?? null,
            "step_name" => $step->name ?? null,
        ]);

        $transactionTypes = collect($step->workflowActionSteps)
            ->pluck("transaction_type_code")
            ->filter()
            ->unique()
            ->values()
            ->all();

        Log::info("[WORKFLOW:STATUS] Transaction types resolved", [
            "instance_id" => $instance->id,
            "transaction_types" => $transactionTypes,
        ]);

        $response = [
            "status" => $instance->status,
            "step" => $step->name,
            "transaction_types" => $transactionTypes,
        ];

        Log::info("[WORKFLOW:STATUS] End", $response);

        return $response;
    }

    public function checkIfInjectDepartments(
        Request $request,
        string $documentTypeId
    ) {
        // On récupère l'ID du workflow actif lié via la table pivot
        $workflowIds = DocumentTypeWorkflow::where(
            "document_type_id",
            $documentTypeId
        )
            ->get()
            ->pluck("workflow_id");

        if ($workflowIds) {
            $workflow = Workflow::with([
                "steps",
                "steps.workflowActionSteps.workflowAction",
            ])
                ->whereIN("id", $workflowIds)
                ->where("active", true)
                ->first();

            $workflow = Workflow::with([
                "steps" => function ($query) {
                    $query
                        ->where("position", 1)
                        ->with("workflowActionSteps.workflowAction");
                },
            ])
                ->whereIn("id", $workflowIds)
                ->where("active", true)
                ->first();

            $secondStep =
                $workflow && $workflow->steps->count()
                    ? $workflow->steps->first()
                    : null;

            return response()->json([
                "success" => true,
                "step" => $secondStep,
            ]);
        } else {
            return response()->json([
                "success" => false,
                "message" => "Aucun workflow actif pour ce type de document",
            ]);
        }
    }

    // Récupérer les étapes d’un workflow donné
    public function steps($id): JsonResponse
    {
        try {
            $workflow = Workflow::with("steps")->findOrFail($id);

            return response()->json([
                "success" => true,
                "data" => $workflow->steps,
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getConfigurableStatusLabels()
    {
        $labels = WorkflowStatusLabel::where("is_configurable", true)
            ->orderBy("label")
            ->get(["id", "label", "emoji", "color"]);

        return response()->json(
            [
                "success" => true,
                "data" => $labels,
            ],
            200
        );
    }

    /**
     * Récupère tous les status labels pour les workflows
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusLabels(Request $request)
    {
        try {
            $query = WorkflowStatusLabel::query();

            if ($request->input("status_types")) {
                $query->whereIn("status_type", $request->input("status_types"));
            }
            // Récupération des labels triés par label
            $labels = $query->orderBy("label")->get();

            // Formatage pour le frontend (optionnel)
            $formatted = $labels->map(function ($label) {
                return [
                    "id" => $label->id,
                    "label" => $label->label,
                    "emoji" => $label->emoji,
                    "color" => $label->color,
                ];
            });

            return response()->json(
                [
                    "success" => true,
                    "data" => $formatted,
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Erreur lors de la récupération des status labels",
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }

    public function oldgetStatusLabels(Request $request)
    {
        return response()->json([
            ["code" => "PENDING", "label" => "En cours de validation"],
            ["code" => "PENDING", "label" => "En attente de paiement"],
            ["code" => "COMPLETE", "label" => "Payée"],
        ]);

        $documentTypes = $request->input("documentTypes", []);
        $token = $request->bearerToken();

        if (empty($documentTypes)) {
            return response()->json([]);
        }

        // 🔹 appel au document service

        $response = Http::acceptJson()
            ->withHeaders([
                "Authorization" => "Bearer $token",
            ])
            ->get(
                config("services.document_service.base_url") .
                    "/document_types/getByRelation",
                [
                    "relations" => $documentTypes,
                ]
            );

        if (!$response->successful()) {
            return response()->json(
                [
                    "error" => "Unable to fetch document types",
                    "body" => $response->body(),
                ],
                500
            );
        }

        $types = $response->json()["data"];

        $allTypes = [];

        foreach ($types as $type) {
            // if (!empty($type['relation_name'])) {

            //     $relations = explode('.', $type['relation_name']);

            //     foreach ($relations as $relation) {
            //         $allTypes[] = $relation;
            //     }

            // } else {
            $allTypes[] = $type["id"];
            // }
        }

        $allTypes = array_unique($allTypes);

        // 🔹 récupérer les status labels liés à ces types
        //  $labels = WorkflowStep::join('workflows', 'workflows.id', '=', 'workflow_steps.workflow_id')
        //     ->join('document_type_workflows', 'document_type_workflows.workflow_id', '=', 'workflows.id')
        //     ->where('workflow_steps.is_archived_step', 0)
        //     ->where('workflow_steps.position', '>', 0)   // exclusion position = 0
        //     ->whereNotNull('workflow_steps.status_label')
        //     ->where('workflows.active', 1)
        //     ->whereIn('document_type_workflows.document_type_id', $allTypes)
        //     ->distinct()
        //     ->orderBy('workflow_steps.status_label')
        //     ->pluck('workflow_steps.status_label');

        $labels = WorkflowStep::join(
            "workflows",
            "workflows.id",
            "=",
            "workflow_steps.workflow_id"
        )
            ->join(
                "document_type_workflows",
                "document_type_workflows.workflow_id",
                "=",
                "workflows.id"
            )
            ->where("workflow_steps.is_archived_step", 0)
            ->where("workflow_steps.position", ">", 0)
            ->whereNotNull("workflow_steps.status_label")
            ->where("workflows.active", 1)
            ->whereIn("document_type_workflows.document_type_id", $allTypes)
            ->select(
                "workflow_steps.status_label as code",
                DB::raw("UPPER(workflow_steps.status_label) as label")
            )
            ->distinct()
            ->orderBy("workflow_steps.status_label")
            ->get();

        return response()->json($labels);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function getByDocumentType($documentTypeId)
    {
        try {
            /*  return $documentTypeWorkflow = DocumentTypeWorkflow::where('document_type_id', $documentTypeId)
            ->with(['workflow'=>function ($query)  {
                $query->whereActive(true);
            },'workflow.steps'=>function ($query)  {
                   $query->orderBy("position" , "desc");
               }])
            ->get();*/

            $workflowIds = DocumentTypeWorkflow::where(
                "document_type_id",
                $documentTypeId
            )->pluck("workflow_id");

            if (count($workflowIds) == 0) {
                // Pas de workflow pour ce type de document → on renvoie id null
                return response()->json([
                    "id" => null,
                    "message" => "Aucun workflow associé à ce type de document",
                ]);
            }

            $workflow = Workflow::whereIn("id", $workflowIds)
                ->with("steps")
                ->with("transitions.conditions")
                ->where("active", true)
                ->first();

            return response()->json($workflow);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getDocumentTypeData(int $documentTypeId)
    {
        $response = Http::timeout(10)
            ->withHeaders([
                "Accept" => "application/json",
                "Authorization" => "Bearer " . request()->bearerToken(),
            ])
            ->get(
                config("services.document_service.base_url") .
                    "/documentTypes/{$documentTypeId}"
            );

        if ($response->failed()) {
            return $response->body();
            return null;
        }

        return $response->json() ?? null;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowRequest  $request
     * @return \Illuminate\Http\Response
     */

    public function Stablestore(StoreWorkflowRequest $request)
    {
        DB::beginTransaction();

        // return $request;

        // throw new Exception(json_encode($this->getDocumentTypeData($request->document_type)), 1);

        // $documentTypeData = $this->getDocumentTypeData($request->document_type);

        try {
            // Désactiver les workflows existants pour ce type de document
            // Récupérer les workflow_id liés au type de document
            $workflowIds = DocumentTypeWorkflow::where(
                "document_type_id",
                $request->document_type
            )->pluck("workflow_id");

            // Désactiver ces workflows
            Workflow::whereIn("id", $workflowIds)
                ->where("active", true)
                ->update(["active" => false]);

            // 1️⃣ Créer le workflow
            $workflow = Workflow::create([
                "name" => $request->name,
            ]);

            // 2️⃣ Associer le workflow au type de document
            if ($request->document_type) {
                DocumentTypeWorkflow::create([
                    "workflow_id" => $workflow->id,
                    "document_type_id" => $request->document_type,
                ]);
            }

            $documentTypeData = $this->getDocumentTypeData(
                $request->document_type
            )["data"];

            $child = explode(".", $documentTypeData["relation_name"])[0];

            // Map UUID frontend -> ID BDD pour pouvoir relier les transitions
            $stepIdMap = [];

            // return $request->steps;

            // 3️⃣ Créer les étapes
            foreach ($request->steps as $index => $stepData) {
                $step = WorkflowStep::create([
                    "workflow_id" => $workflow->id,
                    "name" => $stepData["stepName"],
                    "workflow_status_label_id" =>
                        $stepData["stepStatus"] ?? null,
                    "assignment_mode" => $stepData["assignationMode"],
                    "is_payment_step" => $stepData["is_payment_step"],
                    "is_archived_step" => $stepData["is_archived_step"],
                    "assignment_rule" => !empty($stepData["assignmentRule"])
                        ? $stepData["assignmentRule"]
                        : null,
                    //'role_id' => $stepData['roleId'] ?? null,
                    "position" => $index,
                ]);

                $stepIdMap[$stepData["id"]] = $step->id;

                // Sauvegarder les rôles associés (table pivot)
                if (
                    !empty($stepData["roleId"]) &&
                    is_array($stepData["roleId"])
                ) {
                    foreach ($stepData["roleId"] as $roleId) {
                        WorkflowStepRole::create([
                            "workflow_step_id" => $step->id,
                            "role_id" => $roleId,
                        ]);
                    }
                }

                // Sauvegarder les types de pièces jointes requis (table pivot)
                if (
                    !empty($stepData["attachmentTypeRequired"]) &&
                    is_array($stepData["attachmentTypeRequired"])
                ) {
                    // return $stepData["attachmentTypeRequired"];

                    foreach (
                        $stepData["attachmentTypeRequired"]
                        as $attachmentTypeId
                    ) {
                        $WorkflowStepAttachmentType = WorkflowStepAttachmentType::create(
                            [
                                "workflow_step_id" => $step->id,
                                "attachment_type_id" => $attachmentTypeId,
                            ]
                        );

                        // return $WorkflowStepAttachmentType;
                    }
                }
            }

            // return '$request->steps';

            // 4️⃣ Créer les transitions envoyées par le frontend
            foreach ($request->transitions as $transitionData) {
                $fromStep = WorkflowStep::find(
                    $stepIdMap[$transitionData["fromStep"]] ?? null
                );
                $toStep = WorkflowStep::find(
                    $stepIdMap[$transitionData["toStep"]] ?? null
                );

                if (!$fromStep || !$toStep) {
                    continue; // skip si step introuvable
                }

                $transitionName =
                    Str::slug($fromStep->name, "_") .
                    "_to_" .
                    Str::slug($toStep->name, "_");

                $workflowTransion = WorkflowTransition::create([
                    "workflow_id" => $workflow->id,
                    "from_step_id" => $fromStep->id,
                    "to_step_id" => $toStep->id,
                    "name" => $transitionName,
                    "type" => strtolower($transitionData["conditionType"]), // NONE, RETURN, etc.
                    "rules" => $transitionData["conditionExpression"] ?? null,
                    "condition_id" => null,
                ]);

                // 5️⃣ Créer les conditions pour cette transition
                // if (!empty($transitionData["blockingRules"])) {
                //     foreach ($transitionData["blockingRules"] as $rule) {
                //         //return $transitionData['blockingRules'];
                //         //return($rule['value']);
                //         WorkflowCondition::create([
                //             "workflow_step_id" => $fromStep->id,
                //             "workflow_transition_id" => $workflowTransion->id,
                //             "condition_kind" => "BLOCKING",
                //             "condition_type" => $rule["type"] ?? null,
                //             "required_type" => $rule["existsTarget"], //=="attachment" ? "engagment-attachment"  : "payment-attachment", // "App\Models\Misc\AttachmentType",
                //             "required_id" => $rule["value"],
                //             "field" =>
                //                 /*$rule["existsTarget"]==*/ "secondary_attachments.[].attachment_type_id", //: "invoice_provider.ledger_code.ledger_code_type_id",// $rule["field"] ?? null,
                //             "operator" => $rule["operator"] ?? null,
                //             "next_step_id" => null,
                //         ]);
                //     }
                // }
                if (!empty($transitionData["blockingRuleGroups"])) {
                    foreach ($transitionData["blockingRuleGroups"] as $group) {
                        $groupId = $group["id"] ?? Str::uuid()->toString();

                        foreach ($group["rules"] as $rule) {
                            WorkflowCondition::create([
                                "workflow_step_id" => $fromStep->id,
                                "workflow_transition_id" =>
                                    $workflowTransion->id,

                                "group_id" => $groupId, // 🔥 IMPORTANT

                                "condition_kind" => "BLOCKING",
                                "condition_type" => $rule["type"] ?? null,

                                "required_type" =>
                                    $rule["existsTarget"] ?? null,
                                "required_id" => $rule["value"] ?? null,

                                "field" =>
                                    $rule["type"] === "exists"
                                        ? "secondary_attachments.[].attachment_type_id"
                                        : $rule["field"] ?? null,

                                "operator" => $rule["operator"] ?? null,
                                "value" => $rule["value"],
                                // : json_encode([$rule["value"]]),

                                "next_step_id" => null,
                            ]);
                        }
                    }
                }

                // if (!empty($transitionData["pathRules"])) {
                //     foreach ($transitionData["pathRules"] as $rule) {
                //         WorkflowCondition::create([
                //             //'workflow_id' => $workflow->id,
                //             "workflow_step_id" => $fromStep->id,
                //             "workflow_transition_id" => $workflowTransion->id,
                //             "condition_kind" => "PATH",
                //             "condition_type" => $rule["type"] ?? null,
                //             //'required' => $rule['required'] ?? 'yes',
                //             "field" => $child.".".$rule["field"] ?? null,
                //             "operator" => $rule["operator"] ?? null,
                //             "value" => $rule["value"] ?? null,
                //             "next_step_id" => $toStep->id, // $stepIdMap[$rule['nextStep']] ?? null,
                //         ]);
                //     }
                // }

                if (!empty($transitionData["pathRuleGroups"])) {
                    foreach ($transitionData["pathRuleGroups"] as $group) {
                        $groupId = $group["id"] ?? Str::uuid()->toString();

                        foreach ($group["rules"] as $rule) {
                            WorkflowCondition::create([
                                "workflow_step_id" => $fromStep->id,
                                "workflow_transition_id" =>
                                    $workflowTransion->id,

                                "group_id" => $groupId, // 🔥 IMPORTANT

                                "condition_kind" => "PATH",
                                "condition_type" => $rule["type"] ?? null,

                                "field" => isset($rule["field"])
                                    ? $child . "." . $rule["field"]
                                    : null,

                                "operator" => $rule["operator"] ?? null,
                                "value" => isset($rule["value"])
                                    ? (is_array($rule["value"])
                                        ? $rule["value"]
                                        : [$rule["value"]])
                                    : null,

                                "next_step_id" => $toStep->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json(
                [
                    "success" => true,
                    "data" => [
                        "workflow" => $workflow->load(
                            "steps",
                            "transitions.conditions"
                        ),
                    ],
                ],
                201
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function store(StoreWorkflowRequest $request)
    {
        DB::beginTransaction();

        // return $request->validated();

        try {
            /*
        |--------------------------------------------------------------------------
        | 1. Désactiver les workflows existants du document type
        |--------------------------------------------------------------------------
        */

            $workflowIds = DocumentTypeWorkflow::where(
                "document_type_id",
                $request->document_type
            )->pluck("workflow_id");

            Workflow::whereIn("id", $workflowIds)
                ->where("active", true)
                ->update(["active" => false]);

            /*
        |--------------------------------------------------------------------------
        | 2. Créer nouveau workflow
        |--------------------------------------------------------------------------
        */

            $workflow = Workflow::create([
                "name" => $request->name,
                "active" => true,
            ]);

            DocumentTypeWorkflow::create([
                "workflow_id" => $workflow->id,
                "document_type_id" => $request->document_type,
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 IMPORTANT : récupération document type data (pour PATH rules)
        |--------------------------------------------------------------------------
        */

            $documentTypeData =
                $this->getDocumentTypeData($request->document_type)["data"] ??
                null;

            $child = isset($documentTypeData["relation_name"])
                ? explode(".", $documentTypeData["relation_name"])[0]
                : null;

            /*
        |--------------------------------------------------------------------------
        | 3. Charger ancien workflow pour reuse
        |--------------------------------------------------------------------------
        */

            $oldWorkflow = Workflow::whereIn("id", $workflowIds)
                ->where("active", false)
                ->latest()
                ->first();

            $oldSteps = collect();
            $oldStepMap = [];
            $keys = [];

            if ($oldWorkflow) {
                $oldSteps = WorkflowStep::where(
                    "workflow_id",
                    $oldWorkflow->id
                )->get();

                foreach ($oldSteps as $i => $step) {
                    $oldStepSignature = md5(
                        json_encode([
                            $step["name"],
                            $step["assignment_mode"],
                            $step["assignment_rule"] ?? null,
                            $step["is_payment_step"],
                            $step["is_archived_step"],
                        ])
                    );

                    if ($i == 0) {
                        //     return [
                        //     $step["name"],
                        //     $step["assignment_mode"],
                        //     $step["assignment_rule"] ?? null,
                        //     $step["is_payment_step"],
                        //     $step["is_archived_step"],
                        // ];
                    }

                    $keys[] = $oldStepSignature;
                    // $oldStepMap[$step->signature] = $step;
                    $oldStepMap[$oldStepSignature] = $step;
                }
            }

            // return $keys;

            /*
        |--------------------------------------------------------------------------
        | 4. Création steps + reuse actions
        |--------------------------------------------------------------------------
        */

            $stepIdMap = [];

            foreach ($request->steps as $index => $stepData) {
                $signature = md5(
                    json_encode([
                        $stepData["stepName"],
                        $stepData["assignationMode"],
                        $stepData["assignmentRule"] ?? null,
                        filter_var(
                            $stepData["is_payment_step"] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        ),
                        filter_var(
                            $stepData["is_archived_step"] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        ),
                    ])
                );

                //         if ($index == 0) {
                //             return [
                //             $stepData["stepName"],
                //             $stepData["assignationMode"],
                //             $stepData["assignmentRule"] ?? null,
                //             filter_var(
                //     $stepData["is_payment_step"] ?? false,
                //     FILTER_VALIDATE_BOOLEAN
                // ),
                //  filter_var(
                //     $stepData["is_archived_step"] ?? false,
                //     FILTER_VALIDATE_BOOLEAN
                // ),
                //         ];//
                //         }
                // return    $signature;

                $oldStep = $oldStepMap[$signature] ?? null;

                $step = WorkflowStep::create([
                    "workflow_id" => $workflow->id,
                    "name" => $stepData["stepName"],
                    "workflow_status_label_id" =>
                        $stepData["stepStatus"] ?? null,
                    "assignment_mode" => $stepData["assignationMode"],
                    "is_payment_step" => $stepData["is_payment_step"],
                    "is_archived_step" => $stepData["is_archived_step"],
                    "assignment_rule" => $stepData["assignmentRule"] ?? null,
                    "position" => $stepData["stepPosition"],

                    // IMPORTANT
                    "signature" => $signature,
                ]);

                $stepIdMap[$stepData["id"]] = $step->id;

                /*
            |--------------------------------------------------------------------------
            | 4.1 Reuse actions si step identique
            |--------------------------------------------------------------------------
            */

                if ($oldStep) {
                    // return $oldStep;

                    $oldActionSteps = WorkflowActionStep::with([
                        "workflowActionStepEvents.workflowEventAudiences"
                    ])
                    ->where(
                        "workflow_step_id",
                        $oldStep->id
                    )->get();

                    foreach ($oldActionSteps as $oldActionStep) {

                      $newActionStep =  WorkflowActionStep::create([
                            "workflow_action_id" =>    $oldActionStep->workflow_action_id,
                            "workflow_step_id" => $step->id,
                            "permission_required" =>  $oldActionStep->permission_required,
                            "transaction_type_code" =>  $oldActionStep->transaction_type_code,
                        ]);



                           foreach ($oldActionStep->workflowActionStepEvents as $oldEvent) {

                           if (!$newActionStep) {

                        //      throw new Exception(json_encode($newActionStep), 1);

                           }


        $newEvent = WorkflowActionStepEvent::create([
            "workflow_action_step_id" => $newActionStep->id,
            "code" => $oldEvent->code,
            "delivery_mode" => $oldEvent->delivery_mode,
            "handler_class" => $oldEvent->handler_class,
            "config" => $oldEvent->config,
            "is_active" => $oldEvent->is_active,
            "execution_order" => $oldEvent->execution_order,
        ]);

      
        


                foreach ($oldEvent->workflowEventAudiences as $oldAudience) {

            WorkflowEventAudience::create([
                "workflow_action_step_event_id" => $newEvent->id,
                "target_type" => $oldAudience->target_type,
                "target_value" => $oldAudience->target_value,
                "channel" => $oldAudience->channel,
                "recipient_type" => $oldAudience->recipient_type,
                "notification_template_id" => $oldAudience->notification_template_id,
                "active" => $oldAudience->active,
                "metadata" => $oldAudience->metadata,
            ]);
        }
    


                        }





                    }



                     



                    
                }

                /*
            |--------------------------------------------------------------------------
            | 4.2 Roles
            |--------------------------------------------------------------------------
            */

                if (
                    !empty($stepData["roleId"]) &&
                    is_array($stepData["roleId"])
                ) {
                    foreach ($stepData["roleId"] as $roleId) {
                        WorkflowStepRole::create([
                            "workflow_step_id" => $step->id,
                            "role_id" => $roleId,
                        ]);
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | 4.3 Attachments
            |--------------------------------------------------------------------------
            */

                if (!empty($stepData["attachmentTypeRequired"])) {
                    foreach (
                        $stepData["attachmentTypeRequired"]
                        as $attachmentTypeId
                    ) {
                        WorkflowStepAttachmentType::create([
                            "workflow_step_id" => $step->id,
                            "attachment_type_id" => $attachmentTypeId,
                        ]);
                    }
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 5. TRANSITIONS (inchangé)
        |--------------------------------------------------------------------------
        */

            foreach ($request->transitions as $transitionData) {
                $fromStep = WorkflowStep::find(
                    $stepIdMap[$transitionData["fromStep"]] ?? null
                );

                $toStep = WorkflowStep::find(
                    $stepIdMap[$transitionData["toStep"]] ?? null
                );

                if (!$fromStep || !$toStep) {
                    continue;
                }

                $workflowTransition = WorkflowTransition::create([
                    "workflow_id" => $workflow->id,
                    "from_step_id" => $fromStep->id,
                    "to_step_id" => $toStep->id,
                    "name" =>
                        Str::slug($fromStep->name, "_") .
                        "_to_" .
                        Str::slug($toStep->name, "_"),
                    "type" => strtolower($transitionData["conditionType"]),
                    "rules" => $transitionData["conditionExpression"] ?? null,
                ]);

                /*
            |--------------------------------------------------------------------------
            | BLOCKING RULES
            |--------------------------------------------------------------------------
            */

                if (!empty($transitionData["blockingRuleGroups"])) {
                    foreach ($transitionData["blockingRuleGroups"] as $group) {
                        $groupId = $group["id"] ?? Str::uuid()->toString();

                        foreach ($group["rules"] as $rule) {
                            WorkflowCondition::create([
                                "workflow_step_id" => $fromStep->id,
                                "workflow_transition_id" =>
                                    $workflowTransition->id,
                                "group_id" => $groupId,
                                "condition_kind" => "BLOCKING",
                                "condition_type" => $rule["type"] ?? null,
                                "required_type" =>
                                    $rule["existsTarget"] ?? null,
                                "required_id" => $rule["value"] ?? null,
                                "field" =>
                                    $rule["type"] === "exists"
                                        ? "secondary_attachments.[].attachment_type_id"
                                        : $rule["field"] ?? null,
                                "operator" => $rule["operator"] ?? null,
                                "value" => $rule["value"] ?? null,
                            ]);
                        }
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | PATH RULES
            |--------------------------------------------------------------------------
            */

                if (!empty($transitionData["pathRuleGroups"])) {
                    foreach ($transitionData["pathRuleGroups"] as $group) {
                        $groupId = $group["id"] ?? Str::uuid()->toString();

                        foreach ($group["rules"] as $rule) {
                            WorkflowCondition::create([
                                "workflow_step_id" => $fromStep->id,
                                "workflow_transition_id" =>
                                    $workflowTransition->id,
                                "group_id" => $groupId,
                                "condition_kind" => "PATH",
                                "condition_type" => $rule["type"] ?? null,
                                "field" => isset($rule["field"])
                                    ? $child . "." . $rule["field"]
                                    : null,
                                "operator" => $rule["operator"] ?? null,
                                "value" => $rule["value"] ?? null,
                                "next_step_id" => $toStep->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json(
                [
                    "success" => true,
                    // "success" => false,
                    "data" => [
                        "workflow" => $workflow->load(
                            "steps.workflowActionSteps.workflowActionStepEvents.workflowEventAudiences",
                            "steps.workflowActionSteps.workflowAction",
                            "transitions.conditions"
                        ),
                    ],
                ],
                201
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function show(Workflow $workflow)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function edit(Workflow $workflow)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowRequest  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function destroy(Workflow $workflow)
    {
        //
    }
}
