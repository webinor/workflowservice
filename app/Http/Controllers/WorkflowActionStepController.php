<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionStepRequest;
use App\Http\Requests\UpdateWorkflowActionStepRequest;
use App\Models\WorkflowActionStep;
use App\Models\WorkflowInstanceStep;

class WorkflowActionStepController extends Controller
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
     * Récupère toutes les actions d'une étape de workflow
     *
     * @param int $instanceStepId
     */
    public function getActionsByStep(int $documentId , WorkflowInstanceStep $instanceStep)
    {
        $result = [];

        $instanceStep->load("workflowStep");
        // Récupère les actions avec leurs infos workflow et action

        if ($instanceStep->workflowStep->assignment_mode == "STATIC") {
            $stepActions = WorkflowActionStep::with([
                "workflowAction",
                "workflowStep.stepRoles",
                "transition",
            ])
                ->where("workflow_step_id", $instanceStep->workflowStep->id)
                ->get();

            // Cas 2 : statique (roles dans step_roles)
            foreach ($stepActions as $actionStep) {
                //return $stepActions;
                foreach ($actionStep["workflowStep"]["stepRoles"] as $role) {
                    $result[] = [
                        "permission_required" =>
                            $actionStep["permission_required"],
                        "role_id" => $role["role_id"], // depuis step_roles
                        "transition_type" =>
                            $actionStep["transition"]["type"] ?? null,
                        "workflow_action_name" =>
                            $actionStep["workflowAction"]["name"],
                        "workflow_action_label" =>
                            $actionStep["workflowAction"]["action_label"],
                    ];
                }
            }
        } else {
            $instanceStep = WorkflowInstanceStep::with([
                "roles", // → table instance_step_roles (role_id connus)
                "workflowStep.workflowActionSteps.workflowAction", // → actions possibles depuis workflow_step
                "workflowStep.workflowActionSteps.transition",
            ])->findOrFail($instanceStep->id);

            // Cas 1 : dynamique (role_id directement dans instance_step)
            foreach (
                $instanceStep["workflowStep"]["workflowActionSteps"]
                as $actionStep
            ) {
                $result[] = [
                    "permission_required" => $actionStep["permission_required"],
                    "role_id" => $instanceStep["role_id"], // direct depuis instance_step
                    "transition_type" =>
                        $actionStep["transition"]["type"] ?? null,
                    "workflow_action_name" =>
                        $actionStep["workflowAction"]["name"],
                    "workflow_action_label" =>
                        $actionStep["workflowAction"]["action_label"],
                ];
            }
        }

        return response()->json([
            "success" => true,
            "data" => $result,
        ]);
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
     * @param  \App\Http\Requests\StoreWorkflowActionStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionStepRequest  $request
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateWorkflowActionStepRequest $request,
        WorkflowActionStep $workflowActionStep
    ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowActionStep $workflowActionStep)
    {
        //
    }
}
