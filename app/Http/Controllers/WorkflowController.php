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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $workflows = Workflow::whereActive(1)->get();

            return response()->json(["success" => true, "data" => $workflows]);
        } catch (\Throwable $th) {
            throw $th;
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
                    "assignment_mode" => $stepData["assignationMode"],
                    "assignment_rule" => !empty($stepData["assignmentRule"]) ? $stepData["assignmentRule"] : null,
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
