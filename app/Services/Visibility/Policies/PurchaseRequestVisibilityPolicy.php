<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestVisibilityPolicy
    implements VisibilityPolicyInterface
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
        | Accès global papier taxi
        |--------------------------------------------------------------------------
        */

            if (array_intersect(
    [
        'VIEW_ALL_PURCHASE_REQUESTS',

        'VIEW_ALL_FINANCIAL_DOCUMENT_YAOUNDE',
        'VIEW_ALL_FINANCIAL_DOCUMENT_KRIBI',
        // 'VIEW_ALL_FINANCIAL_DOCUMENT_DOUALA'


    ],
    $responsibilities
)) {
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