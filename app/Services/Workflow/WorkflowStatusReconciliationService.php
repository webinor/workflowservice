<?php

namespace App\Services\Workflow;

use App\Models\Signature;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\Workflow\Status\WorkflowStatusManager;
use Illuminate\Support\Facades\DB;

class WorkflowStatusReconciliationService
{
    protected WorkflowStatusManager $workflowStatusManager;
    protected WorkflowTransactionTypeService $workflowTransactionTypeService;

    public function __construct(
        WorkflowStatusManager $workflowStatusManager,
          WorkflowTransactionTypeService $workflowTransactionTypeService
    ) {
        $this->workflowStatusManager = $workflowStatusManager;

    $this->workflowTransactionTypeService =
        $workflowTransactionTypeService;
    }

  


    /**
     * Réconcilie le statut d'une instance workflow.
     *
     * SAFE :
     *   aucune modification en base.
     *
     * EXECUTE :
     *   applique réellement la modification.
     *
     * Chaîne utilisée :
     *
     * Signature
     *     ↓ workflow_instance_step_id
     * WorkflowInstanceStep
     *     ↓ workflow_step_id
     * WorkflowStep
     *     ↓
     * WorkflowActionStep
     *     ↓
     * transaction_type_code
     */
    public function reconcile(
        WorkflowInstance $instance,
        $execute = false
    ): array {

        /*
        |--------------------------------------------------------------------------
        | 1. Vérifier le type de document
        |--------------------------------------------------------------------------
        */

        $documentType =
            $instance->document_type_relation_name;

        $supportedTypes = [
            'taxi_paper',
            'fee_note',
            'regularization_sheet',
        ];

        if (!in_array($documentType, $supportedTypes)) {
            return [
                'updated' => false,
                'old_status' => null,
                'new_status' => null,
                'transaction_type_code' => null,
                'reason' => 'DOCUMENT_TYPE_NOT_SUPPORTED',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Trouver la signature liée à l'instance
        |--------------------------------------------------------------------------
        |
        | Signature
        |     workflow_instance_step_id
        |              ↓
        |      WorkflowInstanceStep
        |
        |--------------------------------------------------------------------------
        */

        $signature = Signature::with([
            'instanceStep.workflowStep.workflowActionSteps',
        ])
            ->whereHas('instanceStep', function ($query) use ($instance) {

                $query->where(
                    'workflow_instance_id',
                    $instance->id
                );

            })
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Aucune signature
        |--------------------------------------------------------------------------
        */

        if (!$signature) {
            return [
                'updated' => false,
                'old_status' => null,
                'new_status' => null,
                'transaction_type_code' => null,
                'reason' => 'NO_SIGNATURE_FOUND',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Remonter vers WorkflowInstanceStep
        |--------------------------------------------------------------------------
        */

        $instanceStep = $signature->instanceStep;

        if (!$instanceStep) {
            return [
                'updated' => false,
                'old_status' => null,
                'new_status' => null,
                'transaction_type_code' => null,
                'reason' => 'NO_INSTANCE_STEP_FOUND',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Sécurité :
        | vérifier que l'instanceStep appartient bien à l'instance.
        |--------------------------------------------------------------------------
        */

        if (
            (int) $instanceStep->workflow_instance_id
            !== (int) $instance->id
        ) {
            return [
                'updated' => false,
                'old_status' => $instanceStep->workflow_status_label_id,
                'new_status' => null,
                'transaction_type_code' => null,
                'reason' => 'INSTANCE_STEP_MISMATCH',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Remonter vers WorkflowStep
        |--------------------------------------------------------------------------
        */

       
        $transactionTypeCode =
                $this->workflowTransactionTypeService
                    ->getTransactionTypeCode($instanceStep);

        if (!$transactionTypeCode) {
    return [
        'updated' => false,
        'old_status' => $instanceStep->workflow_status_label_id,
        'new_status' => null,
        'transaction_type_code' => null,
        'reason' => 'NO_TRANSACTION_TYPE_CODE',
    ];
}

        /*
        |--------------------------------------------------------------------------
        | 8. Déterminer le nouveau statut
        |--------------------------------------------------------------------------
        */

        $newStatusId = $this->resolveStatus(
            $documentType,
            $transactionTypeCode
        );

        if (!$newStatusId) {
            return [
                'updated' => false,
                'old_status' => $instanceStep->workflow_status_label_id,
                'new_status' => null,
                'transaction_type_code' => $transactionTypeCode,
                'reason' => 'NO_STATUS_MAPPING',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Le statut est déjà correct
        |--------------------------------------------------------------------------
        */

        if ($instanceStep->workflow_status_label_id === $newStatusId) {
            return [
                'updated' => false,
                'old_status' => $instanceStep->workflow_status_label_id,
                'new_status' => $newStatusId,
                'transaction_type_code' => $transactionTypeCode,
                'reason' => 'ALREADY_UPDATED',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 10. SAFE MODE
        |--------------------------------------------------------------------------
        */

        if (!$execute) {
            return [
                'updated' => true,
                'old_status' => $instanceStep->workflow_status_label_id,
                'new_status' => $newStatusId,
                'transaction_type_code' => $transactionTypeCode,
                'reason' => 'DRY_RUN',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 11. EXECUTION
        |--------------------------------------------------------------------------
        */

        $oldStatus = $instanceStep->workflow_status_label_id;

        DB::transaction(function () use (
            $instanceStep,
            $documentType
        ) {

            $this->workflowStatusManager->handle(
                $instanceStep,
                $documentType
            );
        });

        /*
        |--------------------------------------------------------------------------
        | 12. Retourner le résultat réel
        |--------------------------------------------------------------------------
        */

        $instanceStep = $instanceStep->fresh();

        return [
            'updated' => true,
            'old_status' => $oldStatus,
            'new_status' => $instanceStep->workflow_status_label_id,
            'transaction_type_code' => $transactionTypeCode,
            'reason' => 'UPDATED',
        ];
    }

    /**
     * Détermine le statut attendu selon :
     *
     * - le type de document
     * - le type de transaction
     */
    protected function resolveStatus(
        string $documentType,
        string $transactionTypeCode
    ): ?string {

        $transactionTypeCode =
            strtoupper(
                trim($transactionTypeCode)
            );

        $status = null;

        switch ($documentType) {

            /*
            |--------------------------------------------------------------------------
            | Papier Taxi
            |--------------------------------------------------------------------------
            */

            case 'taxi_paper':

                return WorkflowStatusLabel::whereCode('PAID_WAITING_CLOSURE')->first()->id ;

            /*
            |--------------------------------------------------------------------------
            | Note de frais
            |--------------------------------------------------------------------------
            */

            case 'fee_note':

                return  WorkflowStatusLabel::whereCode('PAID_WAITING_CLOSURE')->first()->id;

            /*
            |--------------------------------------------------------------------------
            | Fiche à régulariser
            |--------------------------------------------------------------------------
            */

            case 'regularization_sheet':

                switch ($transactionTypeCode) {

                    case 'REGULARIZATION_ADVANCE':

                return  WorkflowStatusLabel::whereCode('PAID_WAITING_REGULARIZATION')->first()->id;

                    case 'REGULARIZATION_SETTLEMENT':

                return  WorkflowStatusLabel::whereCode('WAITING_CLOSURE')->first()->id;

                    default:

                        return null;
                }

            default:

                return null;
        }
    }
}
