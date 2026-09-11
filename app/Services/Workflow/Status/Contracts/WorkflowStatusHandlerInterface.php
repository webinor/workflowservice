<?php

namespace App\Services\Workflow\Status\Contracts;

use App\Models\WorkflowInstanceStep;

interface WorkflowStatusHandlerInterface
{
    public function handle(
        WorkflowInstanceStep $instanceStep
    ): WorkflowInstanceStep;
}