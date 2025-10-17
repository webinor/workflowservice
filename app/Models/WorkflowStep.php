<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        "workflow_id",
        "name",
        "assignment_mode",
        "role_id",
        "position",
        "is_archived_step",
    ];

    protected $casts = [
        "is_archived_step" => "boolean",
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function stepRoles()
    {
        return $this->hasMany(WorkflowStepRole::class);
    }

    public function outgoingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, "from_step_id");
    }

    public function incomingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, "to_step_id");
    }

    /**
     * Relation pivot : une étape peut avoir plusieurs attachment_types
     */
    public function attachmentTypes()
    {
        return $this->hasMany(WorkflowStepAttachmentType::class);
    }

    /**
     * Get all of the workflowActionSteps for the WorkflowStep
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function workflowActionSteps(): HasMany
    {
        return $this->hasMany(WorkflowActionStep::class);
    }

    public function instanceSteps()
    {
        return $this->hasMany(WorkflowInstanceStep::class, "workflow_step_id");
    }
}
