<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class AbsenceVisibilityPolicy
    implements VisibilityPolicyInterface
{
    protected $workflowVisibility;

    public function __construct(
        WorkflowVisibilityService $workflowVisibility
    ) {
        $this->workflowVisibility = $workflowVisibility;
    }

    public function apply(
        Builder $query,
        int $roleId,
        int $userId,
        int $employeeId,
        array $responsibilities = []
    ): Builder {

        /*
        |--------------------------------------------------------------------------
        | Accès global aux absences
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'VIEW_ALL_ABSENCES',
                $responsibilities,
                true
            )
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Workflow
        |--------------------------------------------------------------------------
        */

        $this->workflowVisibility->apply(
            $query,
            $roleId,
            $userId
        );

        /*
        |--------------------------------------------------------------------------
        | TODO :
        |
        | Ajouter ici les règles spécifiques aux absences.
        |--------------------------------------------------------------------------
        */

        return $query;
    }
}