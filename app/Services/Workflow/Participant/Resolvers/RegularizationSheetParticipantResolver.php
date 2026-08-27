<?php

namespace App\Services\Workflow\Participant\Resolvers;

use App\Models\WorkflowInstance;
use App\Services\Workflow\Participant\ParticipantResolver;

class RegularizationSheetParticipantResolver implements ParticipantResolver
{
    public function resolve(WorkflowInstance $instance): array
    {
        $participants = [];

        /*
        |--------------------------------------------------------------------------
        | 1. Identifier l'instance step de la régularisation
        |--------------------------------------------------------------------------
        |
        | On recherche l'instance step qui contient une action step dont
        | transaction_type_code = REGULARIZATION_ADVANCE.
        |
        | La position appartient à WorkflowInstanceStep.
        |
        */

        $regularizationStep = $instance
            ->instance_steps()
            ->whereHas(
                'workflowStep.workflowActionSteps',
                function ($query) {
                    $query->where(
                        'transaction_type_code',
                        'REGULARIZATION_ADVANCE'
                    );
                }
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 2. Récupérer la position de la régularisation
        |--------------------------------------------------------------------------
        */

        $regularizationPosition = $regularizationStep
            ? $regularizationStep->position
            : null;

        /*
        |--------------------------------------------------------------------------
        | 3. Récupérer uniquement les étapes AVANT la régularisation
        |--------------------------------------------------------------------------
        */

        $instanceSteps = $instance
            ->instance_steps()
            ->with([
                'assignments'
            ])
            ->when(
                $regularizationPosition !== null,
                function ($query) use ($regularizationPosition) {

                    $query->where(
                        'position',
                        '<',
                        $regularizationPosition
                    );

                }
            )
            ->orderBy('position')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 4. Construire les participants
        |--------------------------------------------------------------------------
        */

        foreach ($instanceSteps as $instanceStep) {

            foreach ($instanceStep->assignments as $assignment) {

                $isApproved =
                    $assignment->decision === 'APPROVED';

                $participants[] = [

                    /*
                    |--------------------------------------------------------------------------
                    | Rôle métier
                    |--------------------------------------------------------------------------
                    */

                    'type' =>
                        $this->mapSourceValueToType(
                            $assignment->source_value
                        ),

                    /*
                    |--------------------------------------------------------------------------
                    | Nom de l'étape
                    |--------------------------------------------------------------------------
                    */

                    'label' =>
                        $instanceStep->name,

                    /*
                    |--------------------------------------------------------------------------
                    | Informations de décision
                    |--------------------------------------------------------------------------
                    */

                    'decided_at' =>
                        $assignment->decided_at,

                    'status' =>
                        $assignment->decision,

                    /*
                    |--------------------------------------------------------------------------
                    | Signature
                    |--------------------------------------------------------------------------
                    */

                    'signature_visibility' =>
                        $assignment->signature_visibility,

                    'signature_mode' =>
                        $assignment->signature_mode,

                    /*
                    |--------------------------------------------------------------------------
                    | Paraphe
                    |--------------------------------------------------------------------------
                    */

                    'paraph_visibility' =>
                        $assignment->paraph_visibility,

                    'paraph_mode' =>
                        $assignment->paraph_mode,

                    /*
                    |--------------------------------------------------------------------------
                    | Utilisateur
                    |--------------------------------------------------------------------------
                    */

                    'user_id' =>
                        $assignment->user_id,

                    'role_id' =>
                        $assignment->role_id,

                    'name' =>
                        $assignment->user->name ?? null,

                    /*
                    |--------------------------------------------------------------------------
                    | État de signature
                    |--------------------------------------------------------------------------
                    */

                    'signed' =>
                        $isApproved,

                    /*
                    |--------------------------------------------------------------------------
                    | Traçabilité
                    |--------------------------------------------------------------------------
                    */

                    'source_type' =>
                        $assignment->source_type,

                    'source_value' =>
                        $assignment->source_value,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Signataire final
        |--------------------------------------------------------------------------
        |
        | Le signataire final est ajouté séparément s'il existe.
        |
        */

        if ($instance->final_signer_id) {

            $participants[] = [

                'type' =>
                    'SIGNER',

                'label' =>
                    'Signataire final',

                'user_id' =>
                    $instance->final_signer_id,

                'name' =>
                    $instance->finalSigner->name ?? null,

                'status' =>
                    'APPROVED',

                'signed' =>
                    true,
            ];
        }

        return $participants;
    }


    /*
    |--------------------------------------------------------------------------
    | Mapper le source_value vers un type métier
    |--------------------------------------------------------------------------
    */

    private function mapSourceValueToType(
        string $value
    ): string {

        $map = [

            'DIRECT_MANAGER' =>
                'APPROVER',

            'HEAD_OF_DEPARTMENT' =>
                'APPROVER',

            'SIGNATORY' =>
                'SIGNER',

            'OWNER' =>
                'PRIMARY_ACTOR',
        ];

        return $map[$value] ?? 'APPROVER';
    }
}