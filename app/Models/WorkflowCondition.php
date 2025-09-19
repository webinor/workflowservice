<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];


    protected $casts = [
    'required_id' => 'array',
];
}
