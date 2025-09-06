<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowStepRequest;
use App\Http\Requests\UpdateWorkflowStepRequest;
use App\Models\WorkflowStep;

class WorkflowStepController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowStep  $workflowStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowStep $workflowStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowStep  $workflowStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowStep $workflowStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowStepRequest  $request
     * @param  \App\Models\WorkflowStep  $workflowStep
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowStepRequest $request, WorkflowStep $workflowStep)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowStep  $workflowStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowStep $workflowStep)
    {
        //
    }
}
