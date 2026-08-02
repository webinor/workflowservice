<?php

namespace App\Services\Workflow;

use App\Models\WorkflowActionStep;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use Illuminate\Support\Facades\Http;

class WorkflowInstanceResolverService
{
    public function getCurrentStep(
        WorkflowInstance $instance
    ): ?WorkflowInstanceStep {


        if ($instance->status === 'COMPLETE') {
        return $instance
            ->instance_steps()
            ->with(['workflowStep.workflowActionSteps',
            'workflowStatusLabel'])
            ->orderByDesc('position')
            ->first();
    }

        return $instance
            ->instance_steps()
            ->with(["workflowStep.workflowActionSteps",
            'workflowStatusLabel'])
            ->where("status", "PENDING")
            ->orderBy("position", "asc")
            ->first();
    }

    public function resolveWorkflowStatusLabel(
        WorkflowInstance $instance
    ): ?WorkflowStatusLabel {

        $currentStep = $this->getCurrentStep($instance);

        //   throw new \Exception(json_encode($currentStep), 1);

        if (!$currentStep) {
            return null;
        }

        $step = $currentStep->workflowStep;

        // étape paiement
        if ($step->is_payment_step) { //cette fonction permet de savoir le status de paiement 

            $response = Http::withToken(request()->bearerToken())
                ->get(
                    config('services.document_service.base_url')
                    . "/"
                    . $instance->document_id
                    . "/payment-status"
                );

            $paymentStatus = $response->json()['status'];

            // return WorkflowStatusLabel::where(
            //     'code',
            //     $paymentStatus
            // )->first();
        }

              

        if ($currentStep->workflowStatusLabel) {
            return $currentStep->workflowStatusLabel;
        }


        // label configuré sur la step
        if ($step->workflowStatusLabel) {
            return $step->workflowStatusLabel;
        }
        else{

        //   throw new \Exception(json_encode($step), 1);

        }

        return null;
    }

    public function resolveReturnTarget(
    WorkflowInstance $instance,
    WorkflowInstanceStep $currentStep,
    WorkflowActionStep $actionStep
): ?WorkflowInstanceStep
{
    switch ($actionStep->return_target_type) {

        case 'SUBMITTER':
            return $this->resolveSubmitterStep($instance);

        case 'PREVIOUS_STEP':
            // return $this->resolvePreviousStep($instance, $currentStep);

        case 'SPECIFIC_STEP':
            // return $this->resolveSpecificStep(
            //     $instance,
            //     $actionStep->return_step_id
            // );

        case 'LAST_VALIDATOR':
            // return $this->resolveLastValidatorStep($instance);

        default:
            
            return $this->resolveSubmitterStep($instance);
            
            throw new \RuntimeException(
                "Unsupported return target : {$actionStep->return_target_type}"
            );
    }
}

private function resolveSubmitterStep(
    WorkflowInstance $instance
): ?WorkflowInstanceStep
{

// SUBMITTER signifie aujourd'hui "retour à l'étape de soumission", et non "retour au créateur

    return WorkflowInstanceStep::with("workflowStep")
        ->where('workflow_instance_id', $instance->id)
        ->where('position', 0)
        ->first();

    /*
     * Évolution future :
     *
     * Ne plus se baser sur la position 0, mais sur le type de l'étape.
     *
     * Ajouter une colonne `step_type` dans `workflow_steps` avec par exemple :
     *
     * - SUBMISSION
     * - APPROVAL
     * - SIGNATURE
     * - PAYMENT
     * - ARCHIVE
     *
     * Puis remplacer la recherche par :
     *
     * return WorkflowInstanceStep::query()
     *     ->where('workflow_instance_id', $instance->id)
     *     ->whereHas('workflowStep', function ($query) {
     *         $query->where('step_type', 'SUBMISSION');
     *     })
     *     ->first();
     *
     * Cette approche sera plus robuste si l'ordre des étapes devient configurable.
     */
}
}