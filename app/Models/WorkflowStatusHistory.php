<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'model_type',
        'changed_by',
        'old_status',
        'new_status',
        'comment',
    ];


    public function model()
    {
        return $this->morphTo();
    }

}
