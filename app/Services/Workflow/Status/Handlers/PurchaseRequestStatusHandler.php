<?php

namespace App\Services\Workflow\Status\Handlers;

use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\PhysicalSupportingDocumentsService;
use App\Services\Workflow\Status\Contracts\WorkflowStatusHandlerInterface;
use App\Services\Workflow\WorkflowTransactionTypeService;
use Illuminate\Support\Facades\Log;
use Exception;

class PurchaseRequestStatusHandler
    implements WorkflowStatusHandlerInterface
{
    /**
     * Service permettant de récupérer le type de transaction
     * configuré sur le WorkflowActionStep associé
     * au WorkflowInstanceStep.
     */
    protected WorkflowTransactionTypeService $workflowTransactionTypeService;

    /**
     * Service responsable de la confirmation de transmission
     * des justificatifs physiques dans le Document Service.
     */
    protected PhysicalSupportingDocumentsService $physicalSupportingDocumentsService;

    /**
     * Injection des services métier.
     */
    public function __construct(
        WorkflowTransactionTypeService $workflowTransactionTypeService,
        PhysicalSupportingDocumentsService $physicalSupportingDocumentsService
    ) {
        $this->workflowTransactionTypeService =
            $workflowTransactionTypeService;

        $this->physicalSupportingDocumentsService =
            $physicalSupportingDocumentsService;
    }

    /**
     * Confirme la transmission des justificatifs physiques
     * avant l'enregistrement de la signature du bénéficiaire.
     *
     * Règles métier :
     *
     * PURCHASE_ADVANCE
     *     → Aucune confirmation automatique des justificatifs.
     *
     * PURCHASE_SETTLEMENT
     *     → Les justificatifs physiques sont considérés
     *       comme transmis lors de la signature.
     *
     * IMPORTANT :
     * Cette méthode doit être appelée AVANT Signature::create().
     *
     * Le Document Service reste l'unique source de vérité
     * concernant la transmission des justificatifs.
     *
     * @param WorkflowInstanceStep $instanceStep
     * @param int                  $documentId
     * @param string               $documentUuid
     * @param array                $actor
     * @param string               $source
     *
     * @return array|null
     */
    public function confirmPhysicalDocumentsBeforeBeneficiarySignature(
        WorkflowInstanceStep $instanceStep,
        int $documentId,
        string $documentUuid,
        array $actor,
        string $source
    ): ?array {
        /*
        |--------------------------------------------------------------------------
        | 1. Récupération du type de transaction
        |--------------------------------------------------------------------------
        |
        | On utilise le type de transaction configuré sur le workflow.
        | On ne se fie pas uniquement au code transmis par le frontend.
        |
        */

        $transactionTypeCode =
            $this->workflowTransactionTypeService
                ->getTransactionTypeCode($instanceStep);

        if (!$transactionTypeCode) {
            throw new Exception(
                'Impossible de déterminer le type de transaction '
                . 'avant la signature du bénéficiaire.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Normalisation du code
        |--------------------------------------------------------------------------
        */

        $transactionTypeCode = strtoupper(
            trim($transactionTypeCode)
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Vérification de la transaction
        |--------------------------------------------------------------------------
        |
        | Une avance fournisseur ne signifie pas que les justificatifs
        | physiques ont été transmis.
        |
        */

        if ($transactionTypeCode !== 'PURCHASE_SETTLEMENT') {
            Log::info(
                '[PurchaseRequestStatusHandler] '
                . 'Aucune confirmation des justificatifs requise '
                . 'pour cette transaction.',
                [
                    'document_id' => $documentId,
                    'document_uuid' => $documentUuid,
                    'workflow_instance_step_id' => $instanceStep->id,
                    'transaction_type_code' => $transactionTypeCode,
                ]
            );

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Confirmation dans le Document Service
        |--------------------------------------------------------------------------
        |
        | L'appel est effectué avant l'enregistrement de la signature.
        |
        */

        Log::info(
            '[PurchaseRequestStatusHandler] '
            . 'Confirmation de transmission des justificatifs physiques.',
            [
                'document_id' => $documentId,
                'document_uuid' => $documentUuid,
                'workflow_instance_step_id' => $instanceStep->id,
                'transaction_type_code' => $transactionTypeCode,
                'actor_id' => $actor['id'] ?? null,
            ]
        );

        $result = $this->physicalSupportingDocumentsService
            ->markAsReceived(
                $documentId,
                $documentUuid,
                $actor,
                $source,
                $transactionTypeCode
            );

        /*
        |--------------------------------------------------------------------------
        | 5. Vérification du résultat
        |--------------------------------------------------------------------------
        |
        | Le contrôleur décidera de poursuivre ou non l'enregistrement
        | de la signature selon le résultat obtenu.
        |
        */

        if (empty($result['success'])) {
            Log::error(
                '[PurchaseRequestStatusHandler] '
                . 'Échec de la confirmation des justificatifs.',
                [
                    'document_id' => $documentId,
                    'document_uuid' => $documentUuid,
                    'workflow_instance_step_id' => $instanceStep->id,
                    'transaction_type_code' => $transactionTypeCode,
                    'actor_id' => $actor['id'] ?? null,
                    'document_service_status' => $result['status'] ?? null,
                    'document_service_message' => $result['message'] ?? null,
                ]
            );

            return [
                'success' => false,
                'message' => $result['message']
                    ?? 'Impossible de confirmer la transmission des justificatifs.',
                'status' => $result['status'] ?? null,
            ];
        }

        Log::info(
            '[PurchaseRequestStatusHandler] '
            . 'Transmission des justificatifs confirmée.',
            [
                'document_id' => $documentId,
                'document_uuid' => $documentUuid,
                'workflow_instance_step_id' => $instanceStep->id,
                'transaction_type_code' => $transactionTypeCode,
                'document_service_status' => $result['status'] ?? null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Transmission des justificatifs confirmée.',
            'status' => $result['status'] ?? null,
        ];
    }

    /**
     * Met à jour le statut métier d'une demande d'achat
     * en fonction du type de transaction configuré
     * sur son WorkflowActionStep.
     *
     * Logique métier :
     *
     * PURCHASE_ADVANCE
     *     → PAID_WAITING_REGULARIZATION
     *
     * PURCHASE_SETTLEMENT
     *     → RECEIPTS_RECEIVED_WAITING_CLOSURE
     *
     * Le transaction_type_code n'est pas recherché dans Payment.
     *
     * La récupération se fait via :
     *
     * WorkflowInstanceStep
     *      ↓
     * WorkflowStep
     *      ↓
     * WorkflowActionStep
     *      ↓
     * transaction_type_code
     *
     * @param WorkflowInstanceStep $instanceStep
     * @return WorkflowInstanceStep
     */
    public function handle(
        WorkflowInstanceStep $instanceStep
    ): WorkflowInstanceStep {

        /*
        |--------------------------------------------------------------------------
        | 1. Récupération du type de transaction
        |--------------------------------------------------------------------------
        |
        | Le WorkflowTransactionTypeService centralise cette logique
        | afin qu'elle puisse être réutilisée ailleurs dans
        | le Workflow Service.
        |
        */

        $transactionTypeCode =
            $this->workflowTransactionTypeService
                ->getTransactionTypeCode($instanceStep);

        /*
        |--------------------------------------------------------------------------
        | 2. Vérification du type de transaction
        |--------------------------------------------------------------------------
        */

        if (!$transactionTypeCode) {
            throw new Exception(
                'Impossible de déterminer le type de transaction '
                . 'de la demande d\'achat.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Normalisation du code
        |--------------------------------------------------------------------------
        */

        $transactionTypeCode = strtoupper(
            trim($transactionTypeCode)
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Détermination du WorkflowStatusLabel
        |--------------------------------------------------------------------------
        */

        $status = null;

        switch ($transactionTypeCode) {

            /*
            |--------------------------------------------------------------------------
            | PURCHASE_ADVANCE
            |--------------------------------------------------------------------------
            */

            case 'PURCHASE_ADVANCE':

                $status = WorkflowStatusLabel::whereCode(
                    'PAID_WAITING_REGULARIZATION'
                )->first();

                break;

            /*
            |--------------------------------------------------------------------------
            | PURCHASE_SETTLEMENT
            |--------------------------------------------------------------------------
            */

            case 'PURCHASE_SETTLEMENT':

                $status = WorkflowStatusLabel::whereCode(
                    'RECEIPTS_RECEIVED_WAITING_CLOSURE'
                )->first();

                break;

            /*
            |--------------------------------------------------------------------------
            | Type de transaction inconnu
            |--------------------------------------------------------------------------
            */

            default:

                throw new Exception(
                    sprintf(
                        'Code de transaction inconnu pour la demande '
                        . 'd\'achat : "%s".',
                        $transactionTypeCode
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Vérification du statut configuré
        |--------------------------------------------------------------------------
        */

        if (!$status) {
            throw new Exception(
                sprintf(
                    'Aucun WorkflowStatusLabel trouvé pour le code '
                    . 'correspondant au type de transaction "%s".',
                    $transactionTypeCode
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Mise à jour du statut de l'instance step
        |--------------------------------------------------------------------------
        */

        $instanceStep->update([
            'workflow_status_label_id' => $status->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7. Retourner l'instance actualisée
        |--------------------------------------------------------------------------
        */

        return $instanceStep->fresh();
    }
}