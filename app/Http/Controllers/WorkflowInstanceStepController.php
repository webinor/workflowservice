<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowInstanceStepRequest;
use App\Http\Requests\UpdateWorkflowInstanceStepRequest;
use App\Models\WorkflowInstanceStep;

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
