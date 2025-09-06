<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'name',
        'assignment_mode',
        'role_id',
        'position',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
