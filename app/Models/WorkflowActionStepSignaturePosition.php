<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowActionStepSignaturePosition extends Model
{
    use HasFactory;

    protected $fillable = [
    'workflow_action_step_id',

    'resource_type_code',
    'resource_uuid',

    'page',

    'x',
    'y',

    'width',
    'height',

    'rotation',

    'signature_type_code',

    'status',
];

protected $casts = [
    'page' => 'integer',

    'x' => 'float',
    'y' => 'float',

    'width' => 'float',
    'height' => 'float',

    'rotation' => 'float',
];
}
