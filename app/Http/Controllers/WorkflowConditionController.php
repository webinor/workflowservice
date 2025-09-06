<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowConditionRequest;
use App\Http\Requests\UpdateWorkflowConditionRequest;
use App\Models\WorkflowCondition;

class WorkflowConditionController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowConditionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowConditionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowCondition  $workflowCondition
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowCondition $workflowCondition)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowCondition  $workflowCondition
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowCondition $workflowCondition)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowConditionRequest  $request
     * @param  \App\Models\WorkflowCondition  $workflowCondition
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowConditionRequest $request, WorkflowCondition $workflowCondition)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowCondition  $workflowCondition
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowCondition $workflowCondition)
    {
        //
    }
}
