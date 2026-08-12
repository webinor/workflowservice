<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class FeeNoteVisibilityPolicy
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
        | Accès global papier taxi
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'VIEW_ALL_FEE_NOTES',
                $responsibilities,
                true
            )
        ) {
    // throw new \Exception(json_encode($responsibilities), 1);

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