<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowEventAudienceRequest;
use App\Http\Requests\UpdateWorkflowEventAudienceRequest;
use App\Models\WorkflowEventAudience;

class WorkflowEventAudienceController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowEventAudienceRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowEventAudienceRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowEventAudience  $workflowEventAudience
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowEventAudience $workflowEventAudience)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowEventAudience  $workflowEventAudience
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowEventAudience $workflowEventAudience)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowEventAudienceRequest  $request
     * @param  \App\Models\WorkflowEventAudience  $workflowEventAudience
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowEventAudienceRequest $request, WorkflowEventAudience $workflowEventAudience)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowEventAudience  $workflowEventAudience
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowEventAudience $workflowEventAudience)
    {
        //
    }
}
