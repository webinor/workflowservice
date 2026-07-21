<?php

namespace App\Contracts;

use App\Models\WorkflowStep;
use App\Models\WorkflowInstance;
use App\Models\Document;

interface WorkflowQuorumEvaluatorInterface
{
    public function isReached(
        WorkflowStep $step,
        WorkflowInstance $instance,
        array $document
    ): bool;
}