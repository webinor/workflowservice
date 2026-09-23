<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use App\Services\Visibility\WorkflowVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class TaxiPaperVisibilityPolicy
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

        // if (
        //     in_array(
        //         'VIEW_ALL_TAXI_PAPERS',
        //         $responsibilities,
        //         true
        //     )
        // ) {

        //     // throw new \Exception(json_encode($responsibilities), 1);


        //     return $query;
        // }


              if (array_intersect(
    [
        'VIEW_ALL_TAXI_PAPERS',

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

        return 
        // $query;
         $this->workflowVisibility->apply(
            $query,
            $roleId,
            $userId
        );
    }
}