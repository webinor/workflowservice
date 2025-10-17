<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowActionStep extends Model
{
    use HasFactory;

    protected $fillable = [
        "workflow_action_id",
        "workflow_step_id",
        "permission_required",
    ];

    public function workflowAction()
    {
        return $this->belongsTo(WorkflowAction::class, "workflow_action_id");
    }

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, "workflow_step_id");
    }

    public function transition()
    {
        return $this->hasOne(
            WorkflowTransition::class,
            "from_step_id",
            "workflow_step_id"
        );
    }
}
