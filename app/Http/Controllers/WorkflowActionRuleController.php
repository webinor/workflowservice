<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionRuleRequest;
use App\Http\Requests\UpdateWorkflowActionRuleRequest;
use App\Models\WorkflowActionRule;

class WorkflowActionRuleController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowActionRuleRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionRuleRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowActionRule  $workflowActionRule
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowActionRule $workflowActionRule)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowActionRule  $workflowActionRule
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowActionRule $workflowActionRule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionRuleRequest  $request
     * @param  \App\Models\WorkflowActionRule  $workflowActionRule
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowActionRuleRequest $request, WorkflowActionRule $workflowActionRule)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowActionRule  $workflowActionRule
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowActionRule $workflowActionRule)
    {
        //
    }
}
