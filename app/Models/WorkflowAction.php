<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = ['name' , 'action_label' , 'workflow_action_type_id'];

    /**
     * Get the workflowActionType that owns the WorkflowAction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workflowActionType(): BelongsTo
    {
        return $this->belongsTo(WorkflowActionType::class);
    }

}
