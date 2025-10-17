<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WorkflowStepAttachmentType extends Model
{
    use HasFactory;

    protected $fillable = ["workflow_step_id", "attachment_type_id"];

    /**
     * Relation avec WorkflowStep
     */
    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function getAttachmentType()
    {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->get(
                config("services.document_service.base_url") .
                    "/attachment-types/by-id/" .
                    $this->attachment_type_id
            );

        if ($response->successful()) {
            //return $response->json();
            return new AttachmentTypeProxy($response->json());
        }

        return $response->body();

        return "null";
    }
}
