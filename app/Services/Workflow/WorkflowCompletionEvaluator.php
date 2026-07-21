<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstanceStep;
use Exception;
use Illuminate\Support\Collection;

class WorkflowCompletionEvaluator
{
    public function isReached(
        WorkflowInstanceStep $instanceStep,
        Collection $assignments,
        array $documentData
    ): bool {

        $workflowStep = $instanceStep->workflowStep;

        $rule = $workflowStep->completion_rule ?? 'ANY';

        switch ($rule) {

            case 'ANY':

                return $assignments
                    ->where('decision', 'APPROVED')
                    ->isNotEmpty();


            case 'ALL':

                $total = $assignments->count();

                $approved = $assignments
                    ->where('decision', 'APPROVED')
                    ->count();

                return $total > 0 && $approved === $total;


            case 'QUORUM':

                $approved = $assignments
                    ->where('decision', 'APPROVED')
                    ->count();

                $required = $workflowStep->completion_rule_config['required'] ?? 1;

                return $approved >= $required;


            case 'CUSTOM':

                // throw new Exception("Error Processing Request", 1);
                

                $isReached = $this->evaluateCustomRule(
                    $workflowStep->completion_rule_config ?? [],
                    $assignments,
                    $documentData
                );

        // throw new Exception(json_encode($isReached), 1);


                return $isReached;


            default:

                return false;
        }
    }


protected function evaluateCustomRule(
    array $config,
    Collection $assignments,
    array $documentData
): bool {

    if (empty($config['conditions'])) {
        return false;
    }

    // Le montant peut provenir de différents types de documents.
    $amount = (float) data_get($documentData,$config['field'] , 0 );

    // Signatures approuvées uniquement
    $approvedAssignments = $assignments->where('decision', 'APPROVED');
                
    // throw new Exception(json_encode($approvedAssignments), 1);


    // Recherche de la condition correspondant au montant
    foreach ($config['conditions'] as $condition) {

        $min = isset($condition['min_amount'])
            ? (float) $condition['min_amount']
            : null;

        $max = isset($condition['max_amount'])
            ? (float) $condition['max_amount']
            : null;

        $matches =
            ($min === null || $amount >= $min) &&
            ($max === null || $amount < $max);

        // throw new Exception(json_encode($matches), 1);
        

        if (!$matches) {
            continue;
        }



        return $this->evaluateCondition(
            $condition,
            $approvedAssignments
        );
    }

    return false;
}


protected function evaluateCondition(
    array $condition,
    Collection $approvedAssignments
): bool {

    $results = [];

    foreach ($condition['rules'] as $rule) {
        $results[] = $this->evaluateRule(
            $rule,
            $approvedAssignments
        );
    }

    $operator = strtoupper($condition['operator'] ?? 'AND');

    if ($operator === 'OR') {
        return in_array(true, $results, true);
    }

    return !in_array(false, $results, true);
}


protected function evaluateRule(
    array $rule,
    Collection $approvedAssignments
): bool {

    switch ($rule['type']) {

        case 'QUORUM':

            return $approvedAssignments->count()
                >= ($rule['required'] ?? 1);


        case 'ROLE':

            $roles = $rule['roles'] ?? [];

            return $approvedAssignments->contains(function ($assignment) use ($roles) {

                return in_array(
                    (string) $assignment->role_id,
                    array_map('strval', $roles),
                    true
                );

            });


        default:

            return false;
    }
}
}