<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class SupplierInvoiceVisibilityPolicy
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
        | Visibilité financière globale
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'VIEW_ALL_FINANCIAL_DOCUMENTS',
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