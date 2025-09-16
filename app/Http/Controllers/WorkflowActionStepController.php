<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionStepRequest;
use App\Http\Requests\UpdateWorkflowActionStepRequest;
use App\Models\WorkflowActionStep;

class WorkflowActionStepController extends Controller
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

     /**
     * Récupère toutes les actions d'une étape de workflow
     *
     * @param int $stepId
     */
    public function getActionsByStep(int $stepId)
    {
        // Récupère les actions avec leurs infos workflow et action
        $actions = WorkflowActionStep::with(['workflowAction', 'workflowStep',  'transition'])
            ->where('workflow_step_id', $stepId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $actions
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
     * @param  \App\Http\Requests\StoreWorkflowActionStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionStepRequest  $request
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowActionStepRequest $request, WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowActionStep $workflowActionStep)
    {
        //
    }
}
