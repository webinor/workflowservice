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
     * @var array<string, WorkflowStatusHandlerInterface>
     */
    protected array $handlers = [];

    public function __construct(
        PaperTaxiStatusHandler $paperTaxiHandler,
        FeeNoteStatusHandler $feeNoteHandler,
        RegularizationSheetStatusHandler $regularizationSheetHandler
    ) {
        $this->handlers = [
            'taxi_paper' =>
                $paperTaxiHandler,

            'fee_note' =>
                $feeNoteHandler,

            'regularization_sheet' =>
                $regularizationSheetHandler,
        ];
    }

    public function handle(
        WorkflowInstanceStep $instanceStep,
        string $documentTypeRelationName
    ): WorkflowInstanceStep {

      // Les demandes d'absence ne sont pas gérées par ce service.
    if ($documentTypeRelationName === 'absence_request') {
        return $instanceStep;
    }

        if (!isset($this->handlers[$documentTypeRelationName])) {
            throw new RuntimeException(
                sprintf(
                    'Aucun gestionnaire de statut pour le type de document "%s"...',
                    $documentTypeRelationName
                )
            );
        }

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
    string $source = "BENEFICIARY_SIGNATURE"

): ?array {
    if (!isset($this->handlers[$documentTypeRelationName])) {
        throw new RuntimeException(
            sprintf(
                'Aucun gestionnaire de statut pour le type de document "%s".',
                $documentTypeRelationName
            )
        );
    }

    $handler = $this->handlers[$documentTypeRelationName];

    /*
    |--------------------------------------------------------------------------
    | Cette opération est spécifique aux fiches à régulariser.
    |--------------------------------------------------------------------------
    */

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
}