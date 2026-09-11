<?php

namespace App\Services\Visibility;

use Illuminate\Database\Eloquent\Builder;

class WorkflowVisibilityService
{
    /**
     * Applique la visibilité workflow standard.
     *
     * Un utilisateur voit un document si AU MOINS UNE étape
     * de son workflow satisfait l'une des conditions suivantes :
     *
     * - une étape PENDING est attribuée à son rôle
     * - une étape COMPLETE a été approuvée par l'utilisateur
     * - une étape PENDING a été retournée par l'utilisateur
     * - une étape a été BYPASSED par l'utilisateur
     * - une étape a été REJECTED par l'utilisateur
     *
     * IMPORTANT :
     * La recherche est effectuée sur TOUTES les étapes du workflow,
     * et non uniquement sur l'étape actuellement sélectionnée
     * par la requête principale.
     */
    public function apply(
        Builder $query,
        int $roleId,
        int $userId
    ): Builder {

        return $query->whereHas(
            'workflowInstance.instance_steps',
            function ($instance_steps) use (
                $roleId,
                $userId
            ) {

                $instance_steps->where(function ($q) use (
                    $roleId,
                    $userId
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | 1. Une étape PENDING est attribuée au rôle de l'utilisateur
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
                    | 2. L'utilisateur a déjà approuvé une étape
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
                    | 3. L'utilisateur a retourné une étape pour modification
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

                    /*
                    |--------------------------------------------------------------------------
                    | 4. L'utilisateur a bypassé une étape
                    |--------------------------------------------------------------------------
                    */

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
                    | 5. L'utilisateur a rejeté une étape
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
        );
    }
}