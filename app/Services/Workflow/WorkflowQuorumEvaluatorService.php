<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowQuorumEvaluatorInterface;
use App\Models\Document;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;

class WorkflowQuorumEvaluatorService implements WorkflowQuorumEvaluatorInterface
{
    public function isReached(
        WorkflowStep $step,
        WorkflowInstance $instance,
        array $document
    ): bool {

        $signatures = collect(
            $instance->currentStepSignatures()
        );

        $amount = $this->resolveAmount($document);

        return $this->evaluateRules(
            $step,
            $amount,
            $signatures
        );
    }

    protected function resolveAmount(array $document): float
    {
        return 0;
    }

    protected function evaluateRules(
        WorkflowStep $step,
        float $amount,
        $signatures
    ): bool
    {
        return false;
    }
}