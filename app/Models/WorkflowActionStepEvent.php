<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowActionStepEvent extends Model
{
    use HasFactory;

    protected $guarded = []; 


/**
 * Get all of the workflowEventAudiences for the WorkflowActionStepEvent
 *
 * @return \Illuminate\Database\Eloquent\Relations\HasMany
 */
public function workflowEventAudiences(): HasMany
{
    return $this->hasMany(WorkflowEventAudience::class);
}
}
