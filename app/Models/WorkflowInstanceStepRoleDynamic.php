<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceStepRoleDynamic extends Model
{
    use HasFactory; protected $fillable = [
        'workflow_instance_step_id',
        'role_id',
    ];
}
