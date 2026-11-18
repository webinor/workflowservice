<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowInstanceStepAssignment;
use App\Models\WorkflowStep;
use Exception;
use Illuminate\Support\Facades\DB;

class WorkflowInstanceStepGenerator
{
    protected $workflowStepCreationService;

    public function __construct(
        WorkflowStepCreationService $workflowStepCreationService
    ) {
        $this->workflowStepCreationService = $workflowStepCreationService;
    }

    /**
     * Génère les instances des étapes manquantes d'un parcours.
     *
     * La première étape du parcours est activée en PENDING.
     * Les étapes suivantes sont créées en NOT_STARTED.
     *
     * Si une étape existe déjà, elle n'est jamais recréée.
     * Si elle existe en NOT_STARTED et constitue la première
     * étape du parcours, elle est activée.
     *
     * @param WorkflowInstance $workflowInstance
     * @param array $steps
     * @param array $stepRolesByStepId
     * @param array $userConnected
     * @param string $STATUS_PENDING
     * @param string $STATUS_NOT_STARTED
     * @param string $STATUS_COMPLETE
     *
     * @return WorkflowInstanceStep|null
     */
    public function generate(
        WorkflowInstance $workflowInstance,
        array $steps,
        array $stepRolesByStepId,
        array $userConnected,
        string $STATUS_PENDING,
        string $STATUS_NOT_STARTED,
        string $STATUS_COMPLETE
    ): ?WorkflowInstanceStep {

        if (empty($steps)) {
            return null;
        }

        return DB::transaction(function () use (
            $workflowInstance,
            $steps,
            $stepRolesByStepId,
            $userConnected,
            $STATUS_PENDING,
            $STATUS_NOT_STARTED,
            $STATUS_COMPLETE
        ) {

            /*
             * =====================================================
             * 1. Récupérer les étapes déjà matérialisées
             * =====================================================
             */

            $existingSteps = WorkflowInstanceStep::where(
                "workflow_instance_id",
                $workflowInstance->id
            )
                ->lockForUpdate()
                ->get()
                ->keyBy("workflow_step_id");

            /*
             * =====================================================
             * 2. Calculer la prochaine position disponible
             * =====================================================
             */

            $nextPosition = (int) WorkflowInstanceStep::where(
                "workflow_instance_id",
                $workflowInstance->id
            )->max("position") + 1;

            /*
             * =====================================================
             * 3. Parcourir le parcours accessible
             * =====================================================
             */

            $firstStepInstance = null;

            foreach ($steps as $index => $step) {

                if (!$step instanceof WorkflowStep) {
                    throw new Exception(
                        "Une définition du parcours n'est pas un modèle WorkflowStep."
                    );
                }

                $stepId = (int) $step->id;

                /*
                 * =================================================
                 * 3.1. Vérifier si l'étape existe déjà
                 * =================================================
                 */

                if ($existingSteps->has($stepId)) {

                    $stepInstance = $existingSteps->get($stepId);

                    /*
                     * Première étape déjà matérialisée :
                     * l'activer si elle est encore NOT_STARTED.
                     */
                    if ($index === 0) {

                        if ($stepInstance->status === $STATUS_NOT_STARTED) {

                            if ($step->is_archived_step) {

                                $stepInstance->update([
                                    "status" => $STATUS_COMPLETE,
                                    "executed_at" => now(),
                                ]);

                                WorkflowInstanceStepAssignment::where(
                                    "instance_step_id",
                                    $stepInstance->id
                                )->update([
                                    "decision" => "APPROVED",
                                    "decided_at" => now(),
                                ]);

                            } else {

                                $stepInstance->update([
                                    "status" => $STATUS_PENDING,
                                    "due_date" => now()->addHours(
                                        $step->delay_hours ?? 24
                                    ),
                                ]);
                            }
                        }
                    }

                    if ($firstStepInstance === null) {
                        $firstStepInstance = $stepInstance;
                    }

                    continue;
                }

                /*
                 * =================================================
                 * 3.2. Résoudre les rôles de l'étape
                 * =================================================
                 */

                if (!array_key_exists(
                    $stepId,
                    $stepRolesByStepId
                )) {
                    throw new Exception(
                        "Les rôles de l'étape {$stepId} n'ont pas été résolus."
                    );
                }

                $stepRoles = $stepRolesByStepId[$stepId];

                /*
                 * =================================================
                 * 3.3. Déterminer le statut initial
                 * =================================================
                 */

                if ($index === 0 && !$step->is_archived_step) {
                    $initialStatus = $STATUS_PENDING;
                } else {
                    $initialStatus = $STATUS_NOT_STARTED;
                }

                /*
                 * =================================================
                 * 3.4. Créer l'instance de l'étape
                 * =================================================
                 */

                $stepInstance =
                    $this->workflowStepCreationService->activateStep(
                        $step,
                        $stepRoles,
                        $initialStatus,
                        $workflowInstance,
                        $nextPosition,
                        $STATUS_COMPLETE,
                        $userConnected,
                        false
                    );

                $nextPosition++;

                /*
                 * Ajouter l'étape à la collection existante
                 * pour éviter toute duplication pendant le parcours.
                 */
                $existingSteps->put(
                    $stepId,
                    $stepInstance
                );

                if ($firstStepInstance === null) {
                    $firstStepInstance = $stepInstance;
                }
            }

            return $firstStepInstance;
        });
    }
}