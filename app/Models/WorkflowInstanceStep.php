<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_instance_id', 'workflow_step_id', 'role_id','user_id', 'status', 'position'];


    /**
     * Get the workflowInstance that owns the WorkflowInstanceStep
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class,);
    }

}
