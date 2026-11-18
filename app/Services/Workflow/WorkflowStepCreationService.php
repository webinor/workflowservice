<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowInstanceStepAssignment;
use App\Models\WorkflowStep;
use Exception;

class WorkflowStepCreationService
{
    /**
     * Crée une instance d'étape et ses affectations.
     *
     * @param WorkflowStep|array $step
     * @param array $stepRoles
     * @param string $initialStatus
     * @param WorkflowInstance $workflowInstance
     * @param int $index
     * @param string $STATUS_COMPLETE
     * @param array $userConnected
     * @param bool $isInitialGeneration
     *
     * @return WorkflowInstanceStep
     */
    public function activateStep(
        $step,
        array $stepRoles,
        string $initialStatus,
        WorkflowInstance $workflowInstance,
        int $index,
        string $STATUS_COMPLETE,
        array $userConnected,
        bool $isInitialGeneration = true
    ): WorkflowInstanceStep {
        $stepInstance = WorkflowInstanceStep::create([
            "workflow_instance_id" => $workflowInstance->id,
            "workflow_step_id" => $step["id"],
            "status" => $initialStatus,
            "due_date" => now()->addHours($step["delay_hours"] ?? 24),
            "position" => $index,
        ]);

        $stepInstance->load("workflowStep.workflowStatusLabel");

        $assignments = $this->createStepAssignments(
            $stepInstance,
            $step,
            $stepRoles,
            $userConnected,
            $index
        );

        /*
         * L'auto-validation de la première étape ne concerne
         * que la génération initiale du workflow.
         */
        if (
            $isInitialGeneration &&
            (int) $step["position"] === 0
        ) {
            $assignment = $assignments[0] ?? null;

            if ($assignment) {
                $assignment->update([
                    "user_id" => $userConnected["id"],
                    "decision" => "APPROVED",
                    "decided_at" => now(),
                ]);
            }
        }

        /*
         * La complétion automatique de la première étape
         * ne concerne que la génération initiale.
         */
        if ($isInitialGeneration && $index === 0) {
            $hasApproved = WorkflowInstanceStepAssignment::where(
                "instance_step_id",
                $stepInstance->id
            )
                ->where("decision", "APPROVED")
                ->exists();

            if ($hasApproved) {
                $stepInstance->update([
                    "status" => $STATUS_COMPLETE,
                    "executed_at" => now(),
                ]);
            }
        }

        return $stepInstance;
    }

    /**
     * Crée les affectations d'une instance d'étape.
     *
     * @param WorkflowInstanceStep $stepInstance
     * @param WorkflowStep $step
     * @param array $stepRoles
     * @param array $userConnected
     * @param int $index
     *
     * @return array
     */
    protected function createStepAssignments(
        WorkflowInstanceStep $stepInstance,
        WorkflowStep $step,
        array $stepRoles,
        array $userConnected,
        int $index
    ): array {
        $assignmentModels = [];

        /*
         * =========================================================
         * 1. Affectations explicites
         * =========================================================
         */
        $explicitAssignments = $stepRoles["assignments"] ?? [];



        if (!empty($explicitAssignments)) {

        // throw new Exception(json_encode($explicitAssignments), 1);


            foreach ($explicitAssignments as $assignmentData) {
                $roleId = $assignmentData["role_id"] ?? null;
                $userId = $assignmentData["user_id"] ?? null;

                if (!$roleId || !$userId) {
                    continue;
                }

                $assignment = WorkflowInstanceStepAssignment::create([
                    "instance_step_id" => $stepInstance->id,
                    "assigned_user_id" => $userId,
                    "role_id" => $roleId,
                    "source_type" => $step["assignment_mode"],
                    "source_value" =>$step["assignment_rule"] ?? $step["assignment_mode"],
                    "decision" => "PENDING",
                    "can_validate" => true,
                    "can_reject" => true,
                ]);

                $assignmentModels[] = $assignment;
            }

            return $assignmentModels;
        }

        /*
         * =========================================================
         * 2. Affectations basées sur les rôles
         * =========================================================
         */
        if (!isset($stepRoles["roles_ids"])) {
            throw new Exception(json_encode(collect($stepRoles)));
        }

        foreach ($stepRoles["roles_ids"] as $roleId) {
            $userId = null;

            if ($step["assignment_mode"] === "OWNER") {
                $userId = $userConnected["id"];
            }

            $assignment = WorkflowInstanceStepAssignment::create([
                "instance_step_id" => $stepInstance->id,
                "user_id" => $userId,
                "role_id" => $roleId,
                "source_type" => $step["assignment_mode"],
                "source_value" =>
                    $step["assignment_rule"] ?? $step["assignment_mode"],
                "decision" => "PENDING",
                "can_validate" => true,
                "can_reject" => true,
            ]);

            $assignmentModels[] = $assignment;
        }

        return $assignmentModels;
    }
}