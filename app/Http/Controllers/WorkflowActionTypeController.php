<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionTypeRequest;
use App\Http\Requests\UpdateWorkflowActionTypeRequest;
use App\Models\WorkflowActionType;
use Illuminate\Support\Facades\Cache;

class WorkflowActionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
{

    // Cache::forget('workflow_action_types');
    $actions = Cache::remember('workflow_action_types', 3600, function () {
        return WorkflowActionType::select('id', 'code', 'label')
            ->orderBy('label')
            ->get()
            ->map(fn($action) => [
                'id' => $action->id,
                'code' => $action->code,
                'label' => $action->label,
            ]);
    });

    return response()->json(["success"=>true, "data" => $actions]);
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
     * @param  \App\Http\Requests\StoreWorkflowActionTypeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowActionType  $workflowActionType
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowActionType $workflowActionType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowActionType  $workflowActionType
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowActionType $workflowActionType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionTypeRequest  $request
     * @param  \App\Models\WorkflowActionType  $workflowActionType
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowActionTypeRequest $request, WorkflowActionType $workflowActionType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowActionType  $workflowActionType
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowActionType $workflowActionType)
    {
        //
    }
}
