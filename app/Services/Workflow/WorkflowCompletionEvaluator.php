<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstanceStep;
use App\Services\ResponsibilityService;
use App\Services\Workflow\Amount\WorkflowAmountResolver;
use Exception;
use Illuminate\Support\Collection;

class WorkflowCompletionEvaluator
{
    /**
     * Service chargé de calculer le montant d'un document.
     *
     * Le moteur de complétion ne connaît pas la structure
     * financière des différents types de documents.
     */
    protected WorkflowAmountResolver $amountResolver;
    protected ResponsibilityService $responsibility_service;

    public function __construct(
        WorkflowAmountResolver $amountResolver,
        ResponsibilityService $responsibility_service
    ) {
        $this->amountResolver = $amountResolver;
        $this->responsibility_service = $responsibility_service;
    }

    /**
     * Détermine si une étape a atteint sa condition
     * de complétion.
     *
     * Règles disponibles :
     *
     * ANY:
     * Une seule approbation suffit.
     *
     * ALL:
     * Toutes les affectations doivent être approuvées.
     *
     * QUORUM:
     * Un nombre minimum d'utilisateurs distincts doit approuver.
     *
     * CUSTOM:
     * La condition dépend d'une configuration métier.
     *
     * @param WorkflowInstanceStep $instanceStep
     * @param Collection $assignments
     * @param array $documentData
     *
     * @return bool
     */
    public function isReached(
        WorkflowInstanceStep $instanceStep,
        Collection $assignments,
        array $documentData
    ): bool {

        $workflowStep = $instanceStep->workflowStep;

        $rule = $workflowStep->completion_rule ?? "ANY";

        switch ($rule) {

            /*
             * =================================================
             * ANY
             * =================================================
             *
             * Une approbation suffit.
             */
            case "ANY":

                return $assignments
                    ->where("decision", "APPROVED")
                    ->isNotEmpty();

            /*
             * =================================================
             * ALL
             * =================================================
             *
             * Toutes les affectations doivent être approuvées.
             *
             * Nous conservons ici le comportement historique.
             */
            case "ALL":

                $total = $assignments->count();

                $approved = $assignments
                    ->where("decision", "APPROVED")
                    ->count();

                return $total > 0 && $approved === $total;

            /*
             * =================================================
             * QUORUM
             * =================================================
             *
             * On compte les utilisateurs ayant réellement
             * approuvé, et non le nombre de lignes d'affectation.
             *
             * user_id représente le validateur effectif.
             */
            case "QUORUM":

                $approvedCount = $this->countDistinctApprovers(
                    $assignments
                );

                $required = $workflowStep
                    ->completion_rule_config["required"] ?? 1;

                return $approvedCount >= (int) $required;

            /*
             * =================================================
             * CUSTOM
             * =================================================
             *
             * Les conditions peuvent dépendre :
             * - Du montant du document.
             * - Du nombre de signataires.
             * - D'une responsabilité particulière.
             */
            case "CUSTOM":

                // throw new Exception("Error Processing Request", 1);
                

                return $this->evaluateCustomRule(
                    $workflowStep->completion_rule_config ?? [],
                    $assignments,
                    $documentData
                );

            default:

                return false;
        }
    }

    /**
     * Compte les utilisateurs distincts ayant approuvé.
     *
     * Pourquoi utiliser user_id ?
     *
     * assigned_user_id = destinataire de l'affectation.
     * user_id = utilisateur ayant réellement pris la décision.
     *
     * Un même utilisateur peut posséder plusieurs rôles
     * et donc apparaître sur plusieurs affectations.
     *
     * Il ne doit être comptabilisé qu'une seule fois.
     */
    protected function countDistinctApprovers(
        Collection $assignments
    ): int {

        return $assignments
            ->where("decision", "APPROVED")
            ->pluck("user_id")
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * Évalue une configuration CUSTOM.
     *
     * La source du montant est configurable :
     *
     * FIELD:
     * Lit un champ unique.
     *
     * SUM:
     * Additionne un champ dans une collection.
     *
     * SUM_FIELDS:
     * Additionne plusieurs champs.
     *
     * L'ancienne configuration utilisant "field"
     * reste également supportée.
     */
    protected function evaluateCustomRule(
        array $config,
        Collection $assignments,
        array $documentData
    ): bool {

        if (empty($config["conditions"])) {
            return false;
        }

        /*
         * 1. Résolution du montant.
         */
        if (!empty($config["amount_source"])) {


            $amount = $this->amountResolver->resolve(
                $documentData,
                $config["amount_source"]
            );

        throw new Exception($amount, 1);


        } elseif (!empty($config["field"])) {

            /*
             * Compatibilité avec l'ancienne configuration.
             */
            $amount = $this->amountResolver->resolve(
                $documentData,
                [
                    "type" => "FIELD",
                    "field" => $config["field"],
                ]
            );

        } else {

            return false;
        }

        /*
         * 2. On conserve les affectations approuvées.
         */
        $approvedAssignments = $assignments->where(
            "decision",
            "APPROVED"
        );

        /*
         * 3. Recherche de la tranche correspondant au montant.
         *
         * min_amount : borne incluse.
         * max_amount : borne exclue.
         */
        foreach ($config["conditions"] as $condition) {

            $min = isset($condition["min_amount"])
                ? (float) $condition["min_amount"]
                : null;

            $max = isset($condition["max_amount"])
                ? (float) $condition["max_amount"]
                : null;

            $matches =
                ($min === null || $amount >= $min) &&
                ($max === null || $amount < $max);


            if (!$matches) {
                continue;
            }



             $is_ok = $this->evaluateCondition(
                $condition,
                $approvedAssignments
            );

        // throw new Exception(json_encode($is_ok), 1);


            return $is_ok;
        }

        return false;
    }

    /**
     * Évalue plusieurs règles à l'intérieur d'une condition.
     *
     * AND:
     * Toutes les règles doivent être satisfaites.
     *
     * OR:
     * Au moins une règle doit être satisfaite.
     */
    protected function evaluateCondition(
        array $condition,
        Collection $approvedAssignments
    ): bool {

        $rules = $condition["rules"] ?? [];

        if (empty($rules)) {
            return false;
        }

        $results = [];

        foreach ($rules as $rule) {

            $results[] = $this->evaluateRule(
                $rule,
                $approvedAssignments
            );
        }

        $operator = strtoupper(
            $condition["operator"] ?? "AND"
        );

        if ($operator === "OR") {

            return in_array(true, $results, true);
        }

        return !in_array(false, $results, true);
    }

    /**
     * Évalue une règle individuelle.
     *
     * Règles disponibles :
     *
     * QUORUM:
     * Nombre minimum de signataires distincts.
     *
     * ROLE:
     * Au moins une affectation approuvée possède
     * l'un des rôles demandés.
     *
     * RESPONSIBILITY:
     * L'utilisateur affecté au titre de cette responsabilité
     * doit avoir effectivement approuvé.
     */
    protected function evaluateRule(
        array $rule,
        Collection $approvedAssignments
    ): bool {

              


        switch ($rule["type"] ?? "") {

            /*
             * =============================================
             * QUORUM
             * =============================================
             */
            case "QUORUM":

                $approvedCount = $this->countDistinctApprovers(
                    $approvedAssignments
                );

                $required = (int) ($rule["required"] ?? 1);

                return $approvedCount >= $required;

            /*
             * =============================================
             * ROLE
             * =============================================
             */
            case "ROLE":

                $roles = array_map(
                    "strval",
                    $rule["roles"] ?? []
                );

                return $approvedAssignments->contains(
                    function ($assignment) use ($roles) {

                        return in_array(
                            (string) $assignment->role_id,
                            $roles,
                            true
                        );
                    }
                );

            /*
             * =============================================
             * RESPONSIBILITY
             * =============================================
             *
             * Exemple :
             *
             * {
             *     "type": "RESPONSIBILITY",
             *     "responsibility": "CEO"
             * }
             *
             * On recherche une affectation :
             *
             * source_type  = RESPONSIBILITY
             * source_value = CEO
             * decision     = APPROVED
             *
             * On vérifie également que l'utilisateur ayant
             * approuvé est bien celui auquel l'affectation
             * CEO était destinée.
             */
            case "RESPONSIBILITY":

                // $responsibility = strtoupper(
                //     $rule["responsibility"] ?? ""
                // );

              $currentUser = request()->get('user');

                $responsibilities = collect(
                    data_get($currentUser, 'employeeContext.responsibilities', [])
                )->pluck('code')->filter()->values()->all();

                $hasCEOResponsibility = $this->responsibility_service->hasAnyCode(
                    $responsibilities,
                    ['CEO']
                );

        // throw new Exception(json_encode($responsibilities), 1);
        // throw new Exception(json_encode($hasCEOResponsibility), 1);

                if (!$hasCEOResponsibility) {
                    return false;
                }

                return $approvedAssignments->contains(
                    function ($assignment) use ($hasCEOResponsibility) {

                        $sourceType = strtoupper(
                            $assignment->source_type ?? ""
                        );

                        $sourceValue = strtoupper(
                            $assignment->source_value ?? ""
                        );

                        $assignedUserId = $assignment->assigned_user_id
                            ?? null;

                        $approverUserId = $assignment->user_id
                            ?? null;

                        return
                            // $sourceType === "RESPONSIBILITY" &&
                            // $sourceValue === $responsibility &&
                            $hasCEOResponsibility &&
                            $assignedUserId !== null &&
                            $approverUserId !== null &&
                            (int) $assignedUserId === (int) $approverUserId;
                    }
                );

            default:

                return false;
        }
    }
}