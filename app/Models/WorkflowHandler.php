<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowHandler extends Model
{
    protected $fillable = [
        'workflow_event_id',
        'handler_class',
        'priority',
        'is_async',
        'enabled',
    ];

    public function event()
    {
        return $this->belongsTo(
            WorkflowEvent::class,
            'workflow_event_id'
        );
    }
}