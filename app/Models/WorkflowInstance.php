<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'document_id', 'status'];

    public function instance_steps()
    {
        return $this->hasMany(WorkflowInstanceStep::class);
    }
}
