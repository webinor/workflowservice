<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'workflow_id',
                     'from_step_id' ,
                     'to_step_id' ,
                     'name' ,
                     'type' ,
                     'rules',
                     'condition_id',
    ];


    /**
     * Get all of the hasMany for the WorkflowTransition
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class);
    }
}
