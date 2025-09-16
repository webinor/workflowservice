<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowInstanceStepRequest;
use App\Http\Requests\UpdateWorkflowInstanceStepRequest;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WorkflowInstanceStepController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }


public function getWorkflowComments(Request $request ,  $documentId)
{

    $workflowInstance = WorkflowInstance::whereDocumentId($documentId)->first();

    $steps = WorkflowInstanceStep::whereWorkflowInstanceId($workflowInstance->id)
        ->whereHas('histories')
        ->with('histories')
        ->orderBy('created_at', 'asc')
        ->get();

      // FlatMap pour obtenir un tableau plat de toutes les histories
      $histories = $steps->flatMap(function ($step) use($request) {
        return $step->histories->map(function ($history) use ($step , $request) {

            // Appel microservice User pour récupérer l'utilisateur qui a fait le changement
            $userData = null;
            if ($history->changed_by) {
               // $response = Http::get("http://user-service/api/users/{$history->changed_by}");
                $response = Http::acceptJson()->withToken($request->bearerToken())->get(config("services.user_service.base_url")."/{$history->changed_by}");

                $userData = $response->successful() ? $response->json()['user'] : null;
            }

            return [
                'workflow_step_id' => $step->workflow_step_id,
                'workflow_instance_step_id' => $step->id,
                'changed_by' => $history->changed_by,
                'user_name' => $userData['name'] ?? 'Utilisateur inconnu',
               // 'user_role' => $userData['role'] ?? null,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'comment' => $history->comment,
                'created_at' => $history->created_at->format('d/m/Y H:i'),
            ];
        });
    });
    
    return response()->json([
        'success'=>true,
        'workflow_instance_id' => $workflowInstance->id,
        'history' => $histories,
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowInstanceStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowInstanceStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowInstanceStepRequest  $request
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowInstanceStepRequest $request, WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }
}
