<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowStepRoleRequest;
use App\Http\Requests\UpdateWorkflowStepRoleRequest;
use App\Models\WorkflowStepRole;

class WorkflowStepRoleController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowStepRoleRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowStepRoleRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowStepRole  $workflowStepRole
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowStepRole $workflowStepRole)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowStepRole  $workflowStepRole
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowStepRole $workflowStepRole)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowStepRoleRequest  $request
     * @param  \App\Models\WorkflowStepRole  $workflowStepRole
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowStepRoleRequest $request, WorkflowStepRole $workflowStepRole)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowStepRole  $workflowStepRole
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowStepRole $workflowStepRole)
    {
        //
    }
}
