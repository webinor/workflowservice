<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowStatusHistoryRequest;
use App\Http\Requests\UpdateWorkflowStatusHistoryRequest;
use App\Models\WorkflowStatusHistory;

class WorkflowStatusHistoryController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowStatusHistoryRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowStatusHistoryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowStatusHistory  $workflowStatusHistory
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowStatusHistory $workflowStatusHistory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowStatusHistory  $workflowStatusHistory
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowStatusHistory $workflowStatusHistory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowStatusHistoryRequest  $request
     * @param  \App\Models\WorkflowStatusHistory  $workflowStatusHistory
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowStatusHistoryRequest $request, WorkflowStatusHistory $workflowStatusHistory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowStatusHistory  $workflowStatusHistory
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowStatusHistory $workflowStatusHistory)
    {
        //
    }
}
