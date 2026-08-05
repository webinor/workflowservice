<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowCondition extends Model
{
    use HasFactory;


    protected $fillable = [
        //'workflow_id' ,
        'workflow_step_id' ,
        'workflow_transition_id' ,
        'condition_kind',
        'condition_type',
        'required_type' ,
        'required_id' ,
        'field',
        'operator' ,
        'value',
        'next_step_id',
        'group_id',
        'error_message'
    ];


    protected $casts = [
    'required_id' => 'array',
    'value' => 'array',

];

/**
 * Get the transition that owns the transition
 *
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function workflow_transition(): BelongsTo
{
    return $this->belongsTo(WorkflowTransition::class, );
}

}
