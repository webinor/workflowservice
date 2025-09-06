<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentTypeWorkflowRequest;
use App\Http\Requests\UpdateDocumentTypeWorkflowRequest;
use App\Models\DocumentTypeWorkflow;

class DocumentTypeWorkflowController extends Controller
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
     * @param  \App\Http\Requests\StoreDocumentTypeWorkflowRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDocumentTypeWorkflowRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DocumentTypeWorkflow  $documentTypeWorkflow
     * @return \Illuminate\Http\Response
     */
    public function show(DocumentTypeWorkflow $documentTypeWorkflow)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DocumentTypeWorkflow  $documentTypeWorkflow
     * @return \Illuminate\Http\Response
     */
    public function edit(DocumentTypeWorkflow $documentTypeWorkflow)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDocumentTypeWorkflowRequest  $request
     * @param  \App\Models\DocumentTypeWorkflow  $documentTypeWorkflow
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDocumentTypeWorkflowRequest $request, DocumentTypeWorkflow $documentTypeWorkflow)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DocumentTypeWorkflow  $documentTypeWorkflow
     * @return \Illuminate\Http\Response
     */
    public function destroy(DocumentTypeWorkflow $documentTypeWorkflow)
    {
        //
    }
}
