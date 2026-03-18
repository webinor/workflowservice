<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowStatusLabelRequest;
use App\Http\Requests\UpdateWorkflowStatusLabelRequest;
use App\Models\WorkflowStatusLabel;

class WorkflowStatusLabelController extends Controller
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
     * @param  \App\Http\Requests\StoreWorkflowStatusLabelRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowStatusLabelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowStatusLabel  $workflowStatusLabel
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowStatusLabel $workflowStatusLabel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowStatusLabel  $workflowStatusLabel
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowStatusLabel $workflowStatusLabel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowStatusLabelRequest  $request
     * @param  \App\Models\WorkflowStatusLabel  $workflowStatusLabel
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowStatusLabelRequest $request, WorkflowStatusLabel $workflowStatusLabel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowStatusLabel  $workflowStatusLabel
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowStatusLabel $workflowStatusLabel)
    {
        //
    }
}
