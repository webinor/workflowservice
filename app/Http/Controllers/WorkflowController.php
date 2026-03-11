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
use App\Models\WorkflowStepAttachmentType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            ->
            with(['steps.stepRoles', 'steps.attachmentTypes', 'transitions.conditions', 'transitions.fromStep', 'transitions.toStep','documentTypeWorkflow'])
            ->get();


           
        

        $mapped = $workflows->map(function ($workflow) use ($token){

             $attachmentTypesData = collect([]);

                $attachmentTypeIds = $workflow->steps
    ->flatMap(fn($step) => $step->attachmentTypes->pluck('attachment_type_id'))
    ->unique()
    ->values()
    ->all();

    if (sizeof($attachmentTypeIds)>0) {
        # code...
    
// throw new Exception($attachmentTypeIds, 1);


 $response = Http::acceptJson()->withHeaders([
            'Authorization' => "Bearer $token"
        ])->get(config('services.document_service.base_url') . '/get-attachment-types', [
    'ids' => implode(',', $attachmentTypeIds)
]);

// throw new Exception($attachmentTypeIds, 1);
// throw new Exception($response->body(), 1);
    

$attachmentTypesData = collect($response->json()['data'])->keyBy('id');

// throw new Exception($attachmentTypesData, 1);

    }
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'code' => $workflow->code,

                'steps' => $workflow->steps->map(function ($step) use ($attachmentTypesData) {
                    return [
                        'id' => $step->id,
                        'stepName' => $step->name,
                        'position' => $step->position,
                        'stepStatus' => $step->status_label,
                        'assignationMode'=>$step->assignment_mode,
                        'assignmentRule'=>$step->assignment_rule,
                        

                        'roleId' => $step->stepRoles->map(function ($role) {
                            
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

                         'attachmentTypeCategoryRequiredId' => $step->attachmentTypes->map(function ($attachmentType) use ($attachmentTypesData) {

    // Récupère les infos du microservice
    $docType = $attachmentTypesData[$attachmentType->attachment_type_id] ?? null;

    return [
        'id' => $attachmentType->attachment_type_id,
        'name' => $docType['name'] ?? null,
        'slug' => $docType['slug'] ?? null,
        'category_id' => $docType['attachment_type_category_id'] ?? null,
        'category' => Str::lower($docType['attachment_type_category']['name']."-attachment") ?? "",
    ];
})
                    ];
                }),

                'transitions' => $workflow->transitions->map(function ($transition) {
                    return [
                        'id' => $transition->id,
                        'fromStep' => $transition->from_step_id,
                        'toStep' => $transition->to_step_id,
                        'name' => $transition->name,
                        'conditionType' => Str::upper($transition->type),

                        'blockingRules' => $transition->conditions
            ->where('condition_kind', 'BLOCKING')
            ->map(function ($condition) {
                return [
                    'id' => $condition->id,
                    'type' => $condition->condition_type, // exists / comparison
                    'existsTarget' => $condition->required_type,
                    'value' => collect($condition->required_id)
                    ->map(fn($v) => (string) $v),
                    'operator' => $condition->operator,
                    'field' => $condition->field ?? null,
                ];
            })->values(),



            // 🔵 Path Rules
        'pathRules' => $transition->conditions
            ->where('condition_kind', 'PATH')
            ->map(function ($condition) {
                return [
                    'id' => $condition->id,
                    'type' => $condition->condition_type, // comparison
                    'field' => $condition->field,
                    'operator' => $condition->operator,
                    'value' => floatval($condition->value),
                    'nextStep' => $condition->next_step_id,
                ];
            })->values(),
                    ];
                }),

                

                 'document_type' => $workflow->documentTypeWorkflow ? [
                    'id' => $workflow->documentTypeWorkflow->id,
                    'document_type_id' => $workflow->documentTypeWorkflow->document_type_id,
                ] : null,

                'created_at' => $workflow->created_at? $workflow->created_at->format('Y-m-d H:i:s') : "",
            ];
        });

        return response()->json([
            "success" => true,
            "data" => $mapped
        ]);

    } catch (\Throwable $th) {

        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la récupération des workflows",
            "error" => $th->getMessage()
        ], 500);
    }
}

    public function checkIfInjectDepartments(Request $request, $documentTypeId)
    {
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


    public function getStatusLabels(Request $request)
{
    $documentTypes = $request->input('documentTypes', []);
    $token = $request->bearerToken();

    if (empty($documentTypes)) {
        return response()->json([]);
    }

    // 🔹 appel au document service
        
    $response = Http::acceptJson()->withHeaders([
            'Authorization' => "Bearer $token"
        ])->get(config('services.document_service.base_url') . '/document_types/getByRelation', [
        'relations' => $documentTypes
    ]);

    if (!$response->successful()) {
        return response()->json([
            'error' => 'Unable to fetch document types',
            'body' => $response->body()
        ], 500);
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
            $allTypes[] = $type['id'];
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


$labels = WorkflowStep::join('workflows', 'workflows.id', '=', 'workflow_steps.workflow_id')
    ->join('document_type_workflows', 'document_type_workflows.workflow_id', '=', 'workflows.id')
    ->where('workflow_steps.is_archived_step', 0)
    ->where('workflow_steps.position', '>', 0)
    ->whereNotNull('workflow_steps.status_label')
    ->where('workflows.active', 1)
    ->whereIn('document_type_workflows.document_type_id', $allTypes)
    ->select(
        'workflow_steps.status_label as code',
        DB::raw('UPPER(workflow_steps.status_label) as label')
    )
    ->distinct()
    ->orderBy('workflow_steps.status_label')
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowRequest  $request
     * @return \Illuminate\Http\Response
     */

    public function store(StoreWorkflowRequest $request)
    {
        DB::beginTransaction();

        //return $request;

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

            // Map UUID frontend -> ID BDD pour pouvoir relier les transitions
            $stepIdMap = [];

            // 3️⃣ Créer les étapes
            foreach ($request->steps as $index => $stepData) {
                $step = WorkflowStep::create([
                    "workflow_id" => $workflow->id,
                    "name" => $stepData["stepName"],
                    "status_label" => $stepData["stepStatus"],
                    "assignment_mode" => $stepData["assignationMode"],
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
                if (!empty($transitionData["blockingRules"])) {
                    foreach ($transitionData["blockingRules"] as $rule) {
                        //return $transitionData['blockingRules'];
                        //return($rule['value']);
                        WorkflowCondition::create([
                            "workflow_step_id" => $fromStep->id,
                            "workflow_transition_id" => $workflowTransion->id,
                            "condition_kind" => "BLOCKING",
                            "condition_type" => $rule["type"] ?? null,
                            "required_type" => $rule["existsTarget"], //=="attachment" ? "engagment-attachment"  : "payment-attachment", // "App\Models\Misc\AttachmentType",
                            "required_id" => $rule["value"],
                            "field" =>
                                /*$rule["existsTarget"]==*/ "secondary_attachments.[].attachment_type_id", //: "invoice_provider.ledger_code.ledger_code_type_id",// $rule["field"] ?? null,
                            "operator" => $rule["operator"] ?? null,
                            "next_step_id" => null,
                        ]);
                    }
                }

                if (!empty($transitionData["pathRules"])) {
                    foreach ($transitionData["pathRules"] as $rule) {
                        WorkflowCondition::create([
                            //'workflow_id' => $workflow->id,
                            "workflow_step_id" => $fromStep->id,
                            "workflow_transition_id" => $workflowTransion->id,
                            "condition_kind" => "PATH",
                            "condition_type" => $rule["type"] ?? null,
                            //'required' => $rule['required'] ?? 'yes',
                            "field" => $rule["field"] ?? null,
                            "operator" => $rule["operator"] ?? null,
                            "value" => $rule["value"] ?? null,
                            "next_step_id" => $toStep->id, // $stepIdMap[$rule['nextStep']] ?? null,
                        ]);
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
