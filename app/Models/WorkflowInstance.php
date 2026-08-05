<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    use HasFactory;

protected $fillable = [
    'workflow_id',
    'document_id',
    'document_uuid',
    'document_type_id',
    'document_type_slug',
    'document_type_version',
    'status',
    'workflow_status_label_id',
];
    public function instance_steps()
    {
        return $this->hasMany(WorkflowInstanceStep::class);
    }

    /**
     * Get the workflowStatusLabel that owns the WorkflowInstance
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workflowStatusLabel(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatusLabel::class);
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

    /**
     * Get all of the signatures for the WorkflowInstance
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class,);
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
