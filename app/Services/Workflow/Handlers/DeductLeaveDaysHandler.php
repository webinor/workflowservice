<?php

namespace App\Services\Workflow\Handlers;


use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;

class DeductLeaveDaysHandler implements WorkflowEventHandlerInterface
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

        $result = $this->documentClient->deductLeaveDays(
            $documentId,
            $instance->id
        );

        return [

            'data' => [
                'result'=>$result,

                'actor' => $documentData['actor_details']['nom'] ?? '',

                'document_reference' =>
                    $documentData['reference'] ?? '',

                'leave_type' =>
                    $documentData['absence_request']['leave_type']['name']
                    ?? 'Absence',

                'departure_date' =>
                    $documentData['absence_request']['departure_date']
                    ?? null,

                'return_date' =>
                    $documentData['absence_request']['return_date']
                    ?? null,

            ],

            'attachments' => [],

        ];
    }
}