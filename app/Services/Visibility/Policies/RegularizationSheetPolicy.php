<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class RegularizationSheetPolicy implements VisibilityPolicyInterface
{
    protected WorkflowVisibilityService $workflowVisibility;

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
        | Accès global fiches à régulariser
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'VIEW_ALL_REGULARIZATION_SHEETS',
                $responsibilities,
                true
            )
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Visibilité workflow standard
        |--------------------------------------------------------------------------
        */

        return $this->workflowVisibility->apply(
            $query,
            $roleId,
            $userId
        );
    }
}