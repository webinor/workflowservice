<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'document_type_id',
        'recipient_mode',
    ];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }
}
