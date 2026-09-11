<?php

namespace App\Services\Workflow\Status\Handlers;

use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\Workflow\Status\Contracts\WorkflowStatusHandlerInterface;
use App\Services\Workflow\WorkflowTransactionTypeService;
use Exception;

class RegularizationSheetStatusHandler
    implements WorkflowStatusHandlerInterface
{
    /**
     * Service permettant de récupérer le type de transaction
     * configuré sur le WorkflowActionStep associé
     * au WorkflowInstanceStep.
     */
    protected WorkflowTransactionTypeService $workflowTransactionTypeService;

    /**
     * Injection du service de récupération du type de transaction.
     */
    public function __construct(
        WorkflowTransactionTypeService $workflowTransactionTypeService
    ) {
        $this->workflowTransactionTypeService =
            $workflowTransactionTypeService;
    }

    /**
     * Met à jour le statut métier d'une fiche à régulariser
     * en fonction du type de transaction configuré
     * sur son WorkflowActionStep.
     *
     * Logique métier :
     *
     * REGULARIZATION_ADVANCE
     *     → PAID_WAITING_REGULARIZATION
     *
     * REGULARIZATION_SETTLEMENT
     *     → WAITING_CLOSURE
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
        |
        | Une fiche à régulariser doit avoir un type de transaction
        | permettant de déterminer son prochain statut métier.
        |
        */

        if (!$transactionTypeCode) {
            throw new Exception(
                'Impossible de déterminer le type de transaction '
                . 'de la fiche à régulariser.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Normalisation du code
        |--------------------------------------------------------------------------
        |
        | Permet d'éviter les problèmes liés à une éventuelle différence
        | de casse ou aux espaces présents dans la configuration.
        |
        | Exemple :
        |
        | "regularization_advance"  → "REGULARIZATION_ADVANCE"
        | " REGULARIZATION_ADVANCE " → "REGULARIZATION_ADVANCE"
        |
        */

        $transactionTypeCode = strtoupper(
            trim($transactionTypeCode)
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Détermination du WorkflowStatusLabel
        |--------------------------------------------------------------------------
        |
        | Le statut métier dépend du type de transaction.
        |
        */

        $status = null;

        switch ($transactionTypeCode) {

            /*
            |--------------------------------------------------------------------------
            | REGULARIZATION_ADVANCE
            |--------------------------------------------------------------------------
            |
            | Une avance a été payée.
            |
            | La fiche reste ensuite en attente de régularisation.
            |
            */

            case 'REGULARIZATION_ADVANCE':

                $status = WorkflowStatusLabel::whereCode(
                    'PAID_WAITING_REGULARIZATION'
                )->first();

                break;

            /*
            |--------------------------------------------------------------------------
            | REGULARIZATION_SETTLEMENT
            |--------------------------------------------------------------------------
            |
            | Le règlement correspondant à la régularisation
            | a été effectué.
            |
            | La fiche passe donc en attente de clôture.
            |
            */

            case 'REGULARIZATION_SETTLEMENT':

                $status = WorkflowStatusLabel::whereCode(
                    'WAITING_CLOSURE'
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
                        'Code de transaction inconnu pour la fiche '
                        . 'à régulariser : "%s".',
                        $transactionTypeCode
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Vérification du statut configuré
        |--------------------------------------------------------------------------
        |
        | On évite d'accéder à $status->id si le label n'existe pas
        | en base.
        |
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
        |
        | Le WorkflowInstanceStep conserve l'identifiant du label
        | correspondant au nouvel état métier.
        |
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
