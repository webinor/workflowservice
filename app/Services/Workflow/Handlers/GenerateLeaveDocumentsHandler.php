<?php

namespace App\Services\Workflow\Handlers;


use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;

class GenerateLeaveDocumentsHandler implements WorkflowEventHandlerInterface
{
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        $this->documentClient = $documentClient;
    }

    public function execute(
        int $documentId,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        $context = $config['context']
            ?? 'leave_validated';

        $result = $this->documentClient->generateLeaveDocuments(
                $documentId,
                $instance->id,
                $context
            );

        return [

            'data' => [

                'actor' =>
                    $documentData['actor_details']['nom'] ?? '',

                'reference' =>
                    $documentData['reference'] ?? '',

                'leave_type' =>
                    $documentData['absence_request']['leave_type']['name']
                    ?? $documentData['absence_request']['type']
                    ?? '',

                'departure_date' =>
                    $documentData['absence_request']['departure_date'] ?? '',

                'return_date' =>
                    $documentData['absence_request']['return_date'] ?? '',

            ],

            'attachments' =>
                $result['attachments'] ?? [],

        ];
    }
}