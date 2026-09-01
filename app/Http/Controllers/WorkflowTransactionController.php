<?php

namespace App\Http\Controllers;

use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;

class WorkflowTransactionController extends Controller
{
    /**
     * Retourne l'utilisateur ayant exécuté le step de paiement
     * d'un document.
     *
     * La source de l'utilisateur est :
     *
     * workflow_instance_step_assignments.user_id
     *
     * et non :
     *
     * workflow_instance_steps.user_id
     */
    public function paymentInitiator(
        string $documentUuid
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | 1. Récupérer l'instance du workflow
        |--------------------------------------------------------------------------
        */

        $instance = WorkflowInstance::query()
            ->where(
                'document_uuid',
                $documentUuid
            )
            ->first();

        if (!$instance) {

            return response()->json([
                'success' => false,

                'message' =>
                    "Aucune instance de workflow trouvée pour le document {$documentUuid}.",

                'data' => null,

            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Récupérer le dernier step de paiement exécuté
        |--------------------------------------------------------------------------
        |
        | IMPORTANT :
        |
        | On ne filtre PAS sur workflow_instance_steps.user_id.
        |
        | L'utilisateur est déterminé via :
        |
        | workflow_instance_step_assignments.user_id
        |
        */

        $paymentStep = $instance
            ->instance_steps()
            ->whereHas(
                'workflowStep',
                function ($query) {

                    $query->where(
                        'is_payment_step',
                        true
                    );
                }
            )
            ->whereNotNull('executed_at')
            ->with([
                'workflowStep',

                'assignments' => function ($query) {

                    $query
                        ->whereNotNull('user_id')
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id');
                },
            ])
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | 3. Aucun step de paiement exécuté
        |--------------------------------------------------------------------------
        */

        if (!$paymentStep) {

            return response()->json([
                'success' => false,

                'message' =>
                    "Aucun step de paiement exécuté trouvé pour le document {$documentUuid}.",

                'data' => null,

            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Récupérer l'assignment
        |--------------------------------------------------------------------------
        */

        $paymentAssignment =
            $paymentStep->assignments->first();


        /*
        |--------------------------------------------------------------------------
        | 5. Aucun utilisateur dans les assignments
        |--------------------------------------------------------------------------
        */

        if (!$paymentAssignment) {

            return response()->json([
                'success' => false,

                'message' =>
                    "Aucun utilisateur trouvé dans les assignments du step de paiement {$paymentStep->id}.",

                'data' => [
                    'document_id' =>
                        $instance->document_id,

                    'document_uuid' =>
                        $instance->document_uuid,

                    'workflow_instance_id' =>
                        $instance->id,

                    'workflow_instance_step_id' =>
                        $paymentStep->id,

                    'workflow_step_id' =>
                        $paymentStep->workflow_step_id,
                ],

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Utilisateur ayant exécuté le paiement
        |--------------------------------------------------------------------------
        */

        $userId =
            (int) $paymentAssignment->user_id;


        /*
        |--------------------------------------------------------------------------
        | 7. Retourner les informations
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [

                'document_id' =>
                    $instance->document_id,

                'document_uuid' =>
                    $instance->document_uuid,

                'workflow_instance_id' =>
                    $instance->id,

                'workflow_instance_step_id' =>
                    $paymentStep->id,

                'workflow_step_id' =>
                    $paymentStep->workflow_step_id,

                /*
                |--------------------------------------------------------------------------
                | Utilisateur
                |--------------------------------------------------------------------------
                */

                'user_id' =>
                    $userId,

                /*
                |--------------------------------------------------------------------------
                | Assignment
                |--------------------------------------------------------------------------
                */

                'assignment_id' =>
                    $paymentAssignment->id,

                'assignment_role_id' =>
                    $paymentAssignment->role_id,

                'assignment_source_type' =>
                    $paymentAssignment->source_type,

                'assignment_source_value' =>
                    $paymentAssignment->source_value,

                'assignment_decision' =>
                    $paymentAssignment->decision,

                /*
                |--------------------------------------------------------------------------
                | Step
                |--------------------------------------------------------------------------
                */

                'executed_at' =>
                    $paymentStep->executed_at,
            ],
        ]);
    }
}