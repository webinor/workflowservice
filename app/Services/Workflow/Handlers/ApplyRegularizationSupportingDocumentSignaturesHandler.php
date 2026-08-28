<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;
use App\Services\Workflow\Contracts\WorkflowHandlerInterface;
use RuntimeException;

class ApplyRegularizationSupportingDocumentSignaturesHandler
    implements WorkflowEventHandlerInterface
{
    protected DocumentServiceClient $documentClientService;

    public function __construct(
        DocumentServiceClient $documentClientService
    ) {

        $this->documentClientService = $documentClientService;
    }

        public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        $document = $context->document ?? null;

        // if (!$document) {
        //     throw new RuntimeException(
        //         'Impossible d’apposer les signatures : document introuvable.'
        //     );
        // }

        if (empty($documentUuid)) {
            throw new RuntimeException(
                'Impossible d’apposer les signatures : UUID du document introuvable.'
            );
        }

        return 
        
        $this->documentClientService
            ->applyRegularizationSupportingDocumentSignatures(
                $documentUuid
            );
    }
}