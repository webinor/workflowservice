<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstance extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'document_id', 'status'];

    public function instance_steps()
    {
        return $this->hasMany(WorkflowInstanceStep::class);
    }

    /**
     * Get the workflow that owns the WorkflowInstance
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, );
    }

    // WorkflowInstance.php
public function lastActiveStep()
{
    return $this->hasOne(WorkflowInstanceStep::class)
        ->where('status', '!=', 'NOT_STARTED')
        ->whereHas('workflowStep', fn($q) => $q->where('is_archived_step', 0))
        ->with('workflowStep')
        ->orderByDesc('position'); // pas de limit nécessaire, hasOne prend la première
}
}
