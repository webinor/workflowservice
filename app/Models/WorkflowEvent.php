<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEvent extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'enabled',
    ];

    public function handlers()
    {
        return $this->hasMany(
            WorkflowHandler::class
        );
    }

    public function actionStepEvents()
    {
        return $this->hasMany(
            WorkflowActionStepEvent::class
        );
    }

    public function steps()
    {
        return $this->belongsToMany(
            WorkflowStep::class,
            'workflow_step_events',
            'workflow_event_id',
            'workflow_step_id'
        );
    }

    public function audiences()
{
    return $this->hasMany(
        WorkflowEventAudience::class,
        'workflow_event_id'
    );
}
}