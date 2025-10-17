<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowStepRequest;
use App\Http\Requests\UpdateWorkflowStepRequest;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
     * Récupérer les attachment types requis pour une étape donnée
     */
    public function attachmentTypes(Request $request, $stepId)
    {
        $documentId = $request->input("documentId");

        if (!$documentId) {
            return response()->json(
                [
                    "message" => "document_id is required",
                ],
                400
            );
        }

        $step = WorkflowStep::with("attachmentTypes")->find($stepId);

        if (!$step) {
            return response()->json(
                [
                    "message" => "Workflow step not found",
                ],
                404
            );
        }

        // Récupérer les IDs des attachment_types requis
        $attachmentTypeRequired = $step->attachmentTypes
            ->pluck("attachment_type_id")
            ->toArray();

        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config("services.document_service.base_url") .
                    "/{$documentId}/missing-attachment-types",
                [
                    "attachment_type_required" => $attachmentTypeRequired,
                ]
            );

        /*
        // On mappe pour retourner les IDs et éventuellement les infos détaillées
        $attachmentTypes = $step->attachmentTypes->map(function ($pivot) {
            // Appel au microservice Document pour récupérer les infos complètes
            return $pivot->getAttachmentType(); // méthode définie dans WorkflowStepAttachmentType
        })->filter(); // supprime les null si l'API échoue
        */

        $missingAttachmentTypes = $response->successful()
            ? $response->json()
            : [];

        return response()->json($missingAttachmentTypes);
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
    public function update(
        UpdateWorkflowStepRequest $request,
        WorkflowStep $workflowStep
    ) {
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
