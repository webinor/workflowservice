<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTypeWorkflow extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_id', 'document_type_id'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
