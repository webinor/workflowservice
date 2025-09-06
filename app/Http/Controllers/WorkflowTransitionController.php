<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowTransitionRequest;
use App\Http\Requests\UpdateWorkflowTransitionRequest;
use App\Models\WorkflowTransition;

class WorkflowTransitionController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowTransitionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowTransitionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowTransition  $workflowTransition
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowTransition $workflowTransition)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowTransition  $workflowTransition
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowTransition $workflowTransition)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowTransitionRequest  $request
     * @param  \App\Models\WorkflowTransition  $workflowTransition
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowTransitionRequest $request, WorkflowTransition $workflowTransition)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowTransition  $workflowTransition
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowTransition $workflowTransition)
    {
        //
    }
}
