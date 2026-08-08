<?php

namespace App\Services\Visibility\Policies;

use App\Services\Visibility\VisibilityPolicyInterface;
use Illuminate\Database\Eloquent\Builder;

class DefaultVisibilityPolicy implements VisibilityPolicyInterface
{
    public function apply(
        Builder $query,
        int $roleId,
        int $userId,
        int $employeeId,
        array $responsibilities = []
    ): Builder {

        /*
        |--------------------------------------------------------------------------
        | Accès global
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'VIEW_ALL_DOCUMENTS',
                $responsibilities,
                true
            )
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Visibilité standard
        |--------------------------------------------------------------------------
        */

        $query->where(function ($q) use (
            $roleId,
            $userId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Étape actuelle
            |--------------------------------------------------------------------------
            */

            $q->where(function ($q) use ($roleId) {

                $q->where(
                    'workflow_instance_steps.status',
                    'PENDING'
                )
                ->whereHas(
                    'assignments',
                    function ($a) use ($roleId) {

                        $a->where(
                            'role_id',
                            $roleId
                        )
                        ->where(
                            'decision',
                            'PENDING'
                        );
                    }
                );

            })

            /*
            |--------------------------------------------------------------------------
            | Historique personnel
            |--------------------------------------------------------------------------
            */

            ->orWhere(function ($q) use ($userId) {

                $q->where(
                    'workflow_instance_steps.status',
                    'COMPLETE'
                )
                ->whereHas(
                    'assignments',
                    function ($a) use ($userId) {

                        $a->where(
                            'user_id',
                            $userId
                        )
                        ->where(
                            'decision',
                            'APPROVED'
                        );
                    }
                );
            });
        });

        return $query;
    }
}