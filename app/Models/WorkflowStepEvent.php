<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStepEvent extends Model
{
    protected $fillable = [
        'workflow_step_id',
        'workflow_event_id',
        'priority',
        'enabled',
    ];

    public function workflowStep()
    {
        return $this->belongsTo(
            WorkflowStep::class,
            'workflow_step_id'
        );
    }

    public function event()
    {
        return $this->belongsTo(
            WorkflowEvent::class,
            'workflow_event_id'
        );
    }
}