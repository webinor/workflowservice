<?php

namespace App\Services\Workflow\Participant;

use App\Models\WorkflowInstance;

interface ParticipantResolver
{
    public function resolve(
        WorkflowInstance $instance
    ): array;
}