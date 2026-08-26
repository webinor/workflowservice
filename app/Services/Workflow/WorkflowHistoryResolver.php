<?php

namespace App\Services\Workflow;

use App\Models\WorkflowStatusHistory;
use Illuminate\Database\Eloquent\Model;

class WorkflowHistoryResolver
{
    /**
     * Retourne le dernier historique correspondant
     * à un modèle polymorphe et éventuellement à un statut.
     *
     * Fonctionne avec :
     * - WorkflowInstance
     * - WorkflowInstanceStep
     * - tout autre modèle utilisant WorkflowStatusHistory
     */
    public function latestFor(
        Model $model,
        ?string $status = null
    ): ?WorkflowStatusHistory {

        return WorkflowStatusHistory::query()
            ->where('model_id', $model->getKey())
            ->where('model_type', $model->getMorphClass())
            ->when(
                $status !== null,
                function ($query) use ($status) {
                    $query->where(
                        'new_status',
                        $status
                    );
                }
            )
            ->latest('id')
            ->first();
    }

    /**
     * Recherche le dernier historique parmi plusieurs modèles.
     *
     * L'ordre des modèles représente la priorité.
     *
     * Exemple :
     *
     * [
     *     $workflowInstanceStep,
     *     $workflowInstance,
     * ]
     *
     * Le Step est donc prioritaire.
     */
    public function latestForModels(
        array $models,
        ?string $status = null
    ): ?WorkflowStatusHistory {

        foreach ($models as $model) {

            if (!$model instanceof Model) {
                continue;
            }

            $history = $this->latestFor(
                $model,
                $status
            );

            if ($history) {
                return $history;
            }
        }

        return null;
    }
}