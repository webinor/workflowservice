<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowEventAudience extends Model
{
    use HasFactory;

    protected $fillable = [
    'workflow_event_id',
    'target_type',
    'target_value',
    'channel',
    'recipient_type',
];


}
