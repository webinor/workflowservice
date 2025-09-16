<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStepRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_step_id',
        'role_id',
    ];

    /**
     * Étape du workflow.
     */
    public function step()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }
}
