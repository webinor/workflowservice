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

        if (!isset($this->handlers[$documentTypeRelationName])) {
            throw new RuntimeException(
                sprintf(
                    'Aucun gestionnaire de statut pour le type de document "%s".',
                    $documentTypeRelationName
                )
            );
        }

        return $this->handlers[$documentTypeRelationName]
            ->handle($instanceStep);
    }
}