<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowActionStep;

class WorkflowTransactionTypeService
{
    /**
     * Retourne le transaction_type_code configuré
     * sur le WorkflowActionStep du WorkflowStep.
     *
     * Chaîne de récupération :
     *
     * WorkflowInstanceStep
     *      ↓ workflow_step_id
     * WorkflowStep
     *      ↓
     * WorkflowActionStep
     *      ↓
     * transaction_type_code
     *
     * Retourne null si :
     * - le WorkflowStep n'existe pas ;
     * - aucun WorkflowActionStep ne possède de transaction_type_code.
     */
    public function getTransactionTypeCode(
        WorkflowInstanceStep $instanceStep
    ): ?string {
        $workflowStep = $instanceStep->workflowStep;

        if (!$workflowStep) {
            return null;
        }

        $actionStep = $workflowStep
            ->workflowActionSteps
            ->first(function ($actionStep) {
                return !empty($actionStep->transaction_type_code);
            });

        if (!$actionStep) {
            return null;
        }

        return $actionStep->transaction_type_code;
    }

    /**
     * Retourne le WorkflowActionStep qui possède
     * le transaction_type_code.
     *
     * Cette méthode est utile lorsque le service appelant
     * a besoin non seulement du code mais également des
     * informations du WorkflowActionStep.
     *
     * Retourne null si aucun WorkflowActionStep
     * avec transaction_type_code n'est trouvé.
     */
    public function getTransactionActionStep(
        WorkflowInstanceStep $instanceStep
    ): ?WorkflowActionStep {
        $workflowStep = $instanceStep->workflowStep;

        if (!$workflowStep) {
            return null;
        }

        return $workflowStep
            ->workflowActionSteps
            ->first(function ($actionStep) {
                return !empty($actionStep->transaction_type_code);
            });
    }

    /**
     * Retourne le contexte complet de la configuration
     * de transaction associée au WorkflowInstanceStep.
     *
     * Le résultat contient :
     *
     * - workflow_action_step_id
     * - workflow_action_id
     * - workflow_step_id
     * - transaction_type_code
     *
     * Retourne null si aucun WorkflowActionStep
     * avec transaction_type_code n'est trouvé.
     */
    public function getTransactionContext(
        WorkflowInstanceStep $instanceStep
    ): ?array {
        $actionStep = $this->getTransactionActionStep(
            $instanceStep
        );

        if (!$actionStep) {
            return null;
        }

        return [
            'workflow_action_step_id' => $actionStep->id,
            'workflow_action_id' => $actionStep->workflow_action_id,
            'workflow_step_id' => $actionStep->workflow_step_id,
            'transaction_type_code' => $actionStep->transaction_type_code,
        ];
    }
}
