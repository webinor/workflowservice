<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionRequest;
use App\Http\Requests\UpdateWorkflowActionRequest;
use App\Models\WorkflowAction;
use App\Models\WorkflowActionStep;
use App\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WorkflowActionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
 public function index(Request $request)
{
    $actionSteps = WorkflowActionStep::query()
        ->with([
            'workflowAction.workflowActionType',
            'workflowStep',
            // 'transition',
            'workflowActionStepEvents',
        ])

        ->when($request->filled('workflow_step_id'), function ($query) use ($request) {
            $query->where('workflow_step_id', $request->workflow_step_id);
        })
        ->when($request->filled('workflow_id'), function ($query) use ($request) {
            $query->whereHas('workflowStep', function ($q) use ($request) {
                $q->where('workflow_id', $request->workflow_id);
            });
        })
        ->orderBy(
            WorkflowAction::select('position')
                ->whereColumn('workflow_actions.id', 'workflow_action_steps.workflow_action_id')
        )
        ->get();

    return response()->json([
        'success' => true,
        'data' => $actionSteps,
    ]);
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

    public function getActionsForStep(Request $request, $workflowStepId)
    {
        $userId = $request->user()->id; // ou Auth::id()

        // $workflowInstance = WorkflowInstance::with("workflow")->whereDocumentId($documentId)->first();

        // Récupère toutes les actions de l'étape
        return $actions = WorkflowActionStep::where(
            "workflow_step_id",
            $workflowStepId
        )->get();

        // Appel au microservice rôle pour récupérer les permissions de l'utilisateur
        $response = Http::withToken($request->bearerToken())->get(
            "http://microservice-roles/api/user-permissions/{$userId}"
        );

        if ($response->failed()) {
            return response()->json(
                ["error" => "Impossible de récupérer les permissions"],
                500
            );
        }

        $userPermissions = $response->json()["permissions"] ?? [];

        // Filtrer les actions selon la permission_required
        $availableActions = $actions->filter(
            fn($a) => in_array($a->permission_required, $userPermissions)
        );

        return response()->json($availableActions);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowActionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionRequest $request)
    {
    //  return   
     $validated = $request->validated();

        try {
            DB::beginTransaction();
            // Créer l’action
            $action = WorkflowAction::create([
                "name" => $validated["actionName"],
                "action_label" => $validated["actionLabel"],
                "workflow_action_type_id" => $validated["workflow_action_type_id"],
                
            ]);

            // Lier l’étape
            $workflowActionStep = WorkflowActionStep::create([
                "workflow_action_id" => $action->id,
                "workflow_step_id" => $validated["workflow_step_id"],
                "permission_required" => $validated["permission_required"],

                "action_step_message" => $validated["action_step_message"],
                "transaction_type_code" => $validated["transaction_type_code"] ?? null,
            ]);

            DB::commit();

            return response()->json(
                [
                    "success" => true,
                    "message" => "Action enregistrée avec succès",
                    "data" => $workflowActionStep,
                ],
                201
            );
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowAction  $workflowAction
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowAction $workflowAction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowAction  $workflowAction
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowAction $workflowAction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionRequest  $request
     * @param  \App\Models\WorkflowAction  $workflowAction
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateWorkflowActionRequest $request,
        WorkflowAction $workflowAction
    ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowAction  $workflowAction
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowAction $workflowAction)
    {
        //
    }
}
