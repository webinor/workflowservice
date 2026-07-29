<?php

namespace App\Services\Workflow;


use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;

class WorkflowPathResolverService
{

public function isReachable(
    WorkflowStep $target,
    WorkflowStep $current,
    array $documentData,
    callable $evaluate,
    array &$visited = []
): bool {

    if (isset($visited[$current->id])) {
        return false;
    }

    $visited[$current->id] = true;

    if ($current->id === $target->id) {
        return true;
    }

    foreach ($current->outgoingTransitions as $transition) {

        if (!$this->transitionMatches($transition, $documentData, $evaluate)) {
            continue;
        }

        if (
            $this->isReachable(
                $target,
                $transition->toStep,
                $documentData,
                $evaluate,
                $visited
            )
        ) {
            return true;
        }
    }

    return false;
}


protected function transitionMatches(
    WorkflowTransition $transition,
    array $documentData,
    callable $evaluate
): bool {

    // Conditions PATH uniquement
    $pathConditions = $transition->conditions
        ->where("condition_kind", "PATH");

    

    // Aucune condition PATH = transition par défaut
    if ($pathConditions->isEmpty()) {
        return true;
    }


    //    throw new \Exception(json_encode($pathConditions), 1);


    // Les conditions d'un même groupe sont en AND
    // Les groupes sont en OR
    $groups = $pathConditions->groupBy("group_id");

    foreach ($groups as $conditions) {

        $allSatisfied = true;

        foreach ($conditions as $condition) {

    //    throw new \Exception(json_encode($condition), 1);

    $isSatify = $evaluate($condition, $documentData);

    //    throw new \Exception(json_encode($isSatify), 1);


            if (!$isSatify) {
                $allSatisfied = false;
                break;
            }

        }

        // Un groupe valide suffit
        if ($allSatisfied) {
            return true;
        }
    }

    return false;
}
    public function resolve(
        WorkflowStep $firstStep,
        array $documentData,
        callable $evaluator
    ): Collection {

        $steps = collect();

        $currentStep = $firstStep;

        $visited = [];

while ($currentStep) {

    if (in_array($currentStep->id, $visited)) {
        throw new \Exception(
            "Cycle détecté sur l'étape {$currentStep->id}"
        );
    }

    $visited[] = $currentStep->id;

    $steps->push($currentStep);

    $currentStep = $this->resolveNextStep(
        $currentStep,
        $documentData,
        $evaluator
    );
}

        return $steps;
    }

    protected function resolveNextStep(
        WorkflowStep $currentStep,
        array $documentData,
        callable $evaluator
    ): ?WorkflowStep {

        $defaultTransition = $currentStep
            ->outgoingTransitions()
            ->whereDoesntHave("conditions", function ($q) {
                $q->where("condition_kind", "PATH");
            })
            ->first();

            logger($currentStep->id);

            if ($defaultTransition) {
            //    throw new \Exception(json_encode($defaultTransition->to_step_id), 1);
            //    throw new \Exception(json_encode($currentStep->id), 1);

}

            

        $pathTransitions = $currentStep
            ->outgoingTransitions()
            ->with([
                "conditions" => function ($q) {
                    $q->where("condition_kind", "PATH");
                },
                "toStep"
            ])
            ->whereHas("conditions")
            ->get();

            // throw new \Exception(json_encode($pathTransitions), 1);


        foreach ($pathTransitions as $transition) {

            $groups = $transition->conditions->groupBy("group_id");

            // throw new \Exception(json_encode($groups), 1);
            

            foreach ($groups as $conditions) {

                $allSatisfied = true;

                foreach ($conditions as $condition) {


            // throw new \Exception(json_encode($evaluator($condition, $documentData)), 1);
                

                    if (!$evaluator($condition, $documentData)) {


                        
                        $allSatisfied = false;
                        break;
                    }

                }

                if ($allSatisfied) {

                    return $transition->toStep;
                }
            }

        }

        return optional($defaultTransition)->toStep;
    }
}