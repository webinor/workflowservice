<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceStepAssignment extends Model
{
    use HasFactory;

    protected $guarded = [];


    /**
     * Get the instanceStep that owns the WorkflowInstanceStepAssignment
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instanceStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceStep::class);
    }
}
