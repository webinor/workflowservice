<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class WorkflowActionType extends Model
{
    use HasFactory;

    protected static function booted()
{
    static::saved(function () {
        Cache::forget('workflow_action_types');
    });

    static::deleted(function () {
        Cache::forget('workflow_action_types');
    });
}
}
