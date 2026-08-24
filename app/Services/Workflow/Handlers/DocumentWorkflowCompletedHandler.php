<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;

class DocumentWorkflowCompletedHandler
    implements WorkflowEventHandlerInterface
{
    public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        /*
         * Informations du bénéficiaire
         */
        $beneficiary = $documentData['actor_details'] ?? [];


        // throw new \Exception(json_encode($documentData['actor_details']), 1);


        return [

            'data' => [

                'beneficiary_name' =>
                    $beneficiary['name']
                    ?? $beneficiary['nom']
                    ?? '',

                'beneficiary_email' =>
                    $beneficiary['email']
                    ?? '',

                'document_id' =>
                    $documentUuid,

                'document_reference' =>
                    $documentData['reference']
                    ?? '',

                'document_type' =>
                    'PAPIER_TAXI',

                'status' =>
                    'VALIDATED',

                'message' =>
                    'Votre demande de papier taxi a été validée.',
            ],

            'attachments' => [],
        ];
    }
}