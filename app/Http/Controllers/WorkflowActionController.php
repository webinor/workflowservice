<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionRequest;
use App\Http\Requests\UpdateWorkflowActionRequest;
use App\Models\WorkflowAction;

class WorkflowActionController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowActionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionRequest $request)
    {
        //
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
    public function update(UpdateWorkflowActionRequest $request, WorkflowAction $workflowAction)
    {
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
