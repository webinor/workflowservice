<?php

namespace App\Services\Workflow;

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
            ->with(['workflowStep.workflowActionSteps'])
            ->orderByDesc('position')
            ->first();
    }

        return $instance
            ->instance_steps()
            ->with(["workflowStep.workflowActionSteps"])
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

              



        // label configuré sur la step
        if ($step->workflowStatusLabel) {
            return $step->workflowStatusLabel;
        }
        else{

        //   throw new \Exception(json_encode($step), 1);

        }

        return null;
    }
}