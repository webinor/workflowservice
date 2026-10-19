<?php

namespace App\Services\Workflow\Status;

use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\Status\Contracts\WorkflowStatusHandlerInterface;
use App\Services\Workflow\Status\Handlers\FeeNoteStatusHandler;
use App\Services\Workflow\Status\Handlers\PaperTaxiStatusHandler;
use App\Services\Workflow\Status\Handlers\RegularizationSheetStatusHandler;
use RuntimeException;

class WorkflowStatusManager
{
    /**
     * Gestionnaires de statut associés aux types de documents.
     *
     * @var array<string, WorkflowStatusHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Types de documents exclus de ce gestionnaire.
     *
     * @var array<int, string>
     */
    protected array $excludedDocumentTypes = [
        'absence_request',
    ];

    public function __construct(
        PaperTaxiStatusHandler $paperTaxiHandler,
        FeeNoteStatusHandler $feeNoteHandler,
        RegularizationSheetStatusHandler $regularizationSheetHandler
    ) {
        $this->handlers = [
            'taxi_paper' => $paperTaxiHandler,
            'fee_note' => $feeNoteHandler,
            'regularization_sheet' => $regularizationSheetHandler,
        ];
    }

    /**
     * Traite le statut d'une étape du workflow.
     *
     * @throws RuntimeException
     */
    public function handle(
        WorkflowInstanceStep $instanceStep,
        string $documentTypeRelationName
    ): WorkflowInstanceStep {

        $this->ensureDocumentTypeIsSupported($documentTypeRelationName);

        return $this->handlers[$documentTypeRelationName]
            ->handle($instanceStep);
    }

    /**
     * Confirme la transmission des justificatifs physiques
     * avant la signature du bénéficiaire.
     *
     * Cette opération concerne uniquement les fiches
     * à régulariser.
     *
     * @return array|null
     */
    public function confirmPhysicalDocumentsBeforeBeneficiarySignature(
        WorkflowInstanceStep $instanceStep,
        string $documentTypeRelationName,
        int $documentId,
        string $documentUuid,
        array $actor,
        string $source = 'BENEFICIARY_SIGNATURE'
    ): ?array {

        $this->ensureDocumentTypeIsSupported($documentTypeRelationName);

        $handler = $this->handlers[$documentTypeRelationName];

        if (!$handler instanceof RegularizationSheetStatusHandler) {
            return null;
        }

        return $handler->confirmPhysicalDocumentsBeforeBeneficiarySignature(
            $instanceStep,
            $documentId,
            $documentUuid,
            $actor,
            $source
        );
    }

    /**
     * Vérifie que le type de document est pris en charge.
     *
     * @throws RuntimeException
     */
    protected function ensureDocumentTypeIsSupported(
        string $documentTypeRelationName
    ): void {
        if (in_array(
            $documentTypeRelationName,
            $this->excludedDocumentTypes,
            true
        )) {
            throw new RuntimeException(
                sprintf(
                    'Le type de document "%s" est exclu du gestionnaire de statut.',
                    $documentTypeRelationName
                )
            );
        }

        if (!isset($this->handlers[$documentTypeRelationName])) {
            throw new RuntimeException(
                sprintf(
                    'Aucun gestionnaire de statut pour le type de document "%s".',
                    $documentTypeRelationName
                )
            );
        }
    }
}