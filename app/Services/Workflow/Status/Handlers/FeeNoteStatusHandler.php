<?php

namespace App\Services\Workflow\Status\Handlers;

use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\Workflow\Status\Contracts\WorkflowStatusHandlerInterface;

class FeeNoteStatusHandler implements WorkflowStatusHandlerInterface
{
    public function handle(
        WorkflowInstanceStep $instanceStep
    ): WorkflowInstanceStep {

        $status = WorkflowStatusLabel::whereCode("PAID_WAITING_CLOSURE")->first();

        $instanceStep->update([
            'workflow_status_label_id' => $status->id,
        ]);

        return $instanceStep->fresh();
    }
}