<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Workflow\Participant\ParticipantService;

class WorkflowParticipantController extends Controller
{
    public function index(
    Request $request,
    int $documentId,
    ParticipantService $service
) {
    $documentType = $request->query('document_type');

    if (!$documentType) {
        return response()->json([
            'success' => false,
            'message' => 'document_type is required'
        ], 422);
    }

    $participants = $service->getParticipants(
        $documentId,
        $documentType
    );

    return response()->json(array_merge([
        'success' => true,
        'document_id' => $documentId,
        'document_type' => $documentType,
    ] , $participants  ));
}
}