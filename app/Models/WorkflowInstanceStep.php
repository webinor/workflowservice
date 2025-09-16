<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_instance_id', 'workflow_step_id', 'role_id','user_id', 'status','executed_at', 'position'];


    /**
     * Get the workflowInstance that owns the WorkflowInstanceStep
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class,);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class,);
    }

    public function histories()
    {
        return $this->morphMany(WorkflowStatusHistory::class, 'model');
    }

    

    public function getCreatedAtAttribute($value)
    {
        if (!$value ) {
            return null; // ou return '';
        }
        return $formatted = Carbon::parse($value)->format('d-m-Y H:i');

    }

    public function getUpdatedAtAttribute($value)
    {
        if (!$value ) {
            return null; // ou return '';
        }
        return $formatted = Carbon::parse($value)->format('d-m-Y H:i');

    }


    public function getExecutedAtAttribute($value)
    {
        if (!$value ) {
            return null; // ou return '';
        }
        return $formatted = Carbon::parse($value)->format('d-m-Y H:i');

    }



}
