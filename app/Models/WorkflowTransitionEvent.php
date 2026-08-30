<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionEvent extends Model
{
    use HasFactory;


    protected $table = 'workflow_transition_events';

    protected $fillable = [
        'workflow_transition_id',
        'workflow_event_id',
        'is_active',
        'execution_order',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowTransition::class,
            'transition_id'
        );
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowEvent::class,
            'event_id'
        );
    }
}
