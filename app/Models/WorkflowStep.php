<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'name',
        'assignment_mode',
        'role_id',
        'position',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
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
    return $this->hasMany(WorkflowInstanceStep::class, 'workflow_step_id');
}
}
