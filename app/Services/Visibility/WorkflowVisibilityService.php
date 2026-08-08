<?php

namespace App\Services\Visibility;

use Illuminate\Database\Eloquent\Builder;

class WorkflowVisibilityService
{
    /**
     * Applique la visibilité workflow standard.
     *
     * Un utilisateur voit :
     *
     * - les documents dont une étape PENDING lui est attribuée via son rôle
     * - les documents qu'il a déjà approuvés
     */
    public function apply(
        Builder $query,
        int $roleId,
        int $userId
    ): Builder {

        return $query->where(function ($q) use (
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
    }
}