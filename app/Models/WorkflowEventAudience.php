<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowEventAudience extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function workflowEvent()
{
    return $this->belongsTo(
        WorkflowEvent::class,
        'workflow_event_id'
    );
}

}
