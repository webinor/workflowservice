<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;
use Illuminate\Support\Facades\Log;

class GenerateLeaveDocumentsHandler implements WorkflowEventHandlerInterface
{
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        $this->documentClient = $documentClient;
    }

    public function execute(
        string $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {
        $context = $config['context']
            ?? ['leave_validated'];

        Log::info(
            "L'utilisateur a généré les documents d'absence.",
            [
                'document_id' =>
                    $documentData['uuid'] ?? $documentUuid,

                'employee_id' =>
                    $documentData['actor_id'] ?? null,

                'generated_by' =>
                    auth()->id(),
            ]
        );

        $result = $this->documentClient->generateLeaveDocuments(
            $documentUuid,
            $instance->id,
            $context
        );

        /*
         * =========================================
         * Informations du document généré
         * =========================================
         */
        $fileName = $result['file_name'] ?? null;

        $fileId = $result['file_id'] ?? null;

        $downloadUrl = $result['url'] ?? null;

        /*
         * =========================================
         * Préparation de la pièce jointe
         * =========================================
         *
         * NotificationService attend :
         *
         * [
         *     [
         *         'id' => ...,
         *         'title' => ...,
         *         'download_url' => ...
         *     ]
         * ]
         *
         */
        $attachments = [];

        if ($fileId || $downloadUrl) {
            $attachments[] = [
                'id' => $fileId,
                'title' => $fileName,
                'download_url' => $downloadUrl,
            ];
        }

        return [
            'data' => [
                'actor' =>
                    $documentData['actor_details']['nom']
                    ?? '',

                'reference' =>
                    $documentData['reference']
                    ?? '',

                'leave_type' =>
                    $documentData['absence_request']['leave_type']['name']
                    ?? $documentData['absence_request']['type']
                    ?? '',

                'departure_date' =>
                    $documentData['absence_request']['departure_date']
                    ?? '',

                'return_date' =>
                    $documentData['absence_request']['return_date']
                    ?? '',

                'document_title' =>
                    $documentData['title']
                    ?? 'Demande de congé',

                'owner_name' =>
                    $documentData['actor_details']['nom']
                    ?? '',

                'document_url' => 
                config('services.frontend_service.base_url')  
                // config('services.frontend_service.base_url')."/".$documentData['document_type']['view_own_route']."/".$documentData['uuid']  
                    // $downloadUrl,
            ],

            'results' => $result,

            'attachments' => $attachments,
        ];
    }
}