<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusHistory;

class WorkflowEventContext
{
    protected $instance;

    protected $step;

    protected $history;

    protected $actorId;

    protected $comment;

    protected $eventCode;

    public function __construct(
        WorkflowInstance $instance,
        ?WorkflowInstanceStep $step = null,
        ?WorkflowStatusHistory $history = null,
        ?int $actorId = null,
        ?string $comment = null,
        ?string $eventCode = null
    ) {
        $this->instance = $instance;
        $this->step = $step;
        $this->history = $history;
        $this->actorId = $actorId;
        $this->comment = $comment;
        $this->eventCode = $eventCode;
    }

    public function getInstance(): WorkflowInstance
    {
        return $this->instance;
    }

    public function getStep(): ?WorkflowInstanceStep
    {
        return $this->step;
    }

    public function getHistory(): ?WorkflowStatusHistory
    {
        return $this->history;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getEventCode(): ?string
    {
        return $this->eventCode;
    }
}