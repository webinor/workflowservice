<?php

namespace App\Services\Workflow\Handlers;


use App\Services\Document\DocumentServiceClient;

class GenerateMissionDocumentsHandler
{
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        $this->documentClient = $documentClient;
    }

    /**
     * Exécution de l'action
     */
    public function execute(
        int $documentId,
        $instance,
        array $config = []
    ) {

        $template = $config['template']
            ?? 'logistics_validated';

        return $this->documentClient
            ->generateMissionDocuments(
                $documentId,
                $instance->id,
                $template
            );
    }
}