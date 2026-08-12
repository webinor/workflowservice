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
     * - les documents qu'il a retournés pour modification
     * - les documents qu'il a rejetés
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
            | 1. Étape actuelle PENDING
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
            | 2. Historique personnel - APPROVED
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
            })

            /*
            |--------------------------------------------------------------------------
            | 3. Retour pour modification
            |--------------------------------------------------------------------------
            */

            ->orWhere(function ($q) use ($userId) {

                $q->where(
                    'workflow_instance_steps.status',
                    'PENDING'
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
                            'RETURNED'
                        );
                    }
                );
            })

            ->orWhere(function ($q) use ($userId) {
    $q->where(
        'workflow_instance_steps.status',
        'BYPASSED'
    )
    ->where(
        'workflow_instance_steps.bypassed_by',
        $userId
    );
})

            /*
            |--------------------------------------------------------------------------
            | 4. Document rejeté par l'utilisateur
            |--------------------------------------------------------------------------
            */

            ->orWhere(function ($q) use ($userId) {

                $q->where(
                    'workflow_instance_steps.status',
                    'REJECTED'
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
                            'REJECTED'
                        );
                    }
                );
            });
        });
    }
}