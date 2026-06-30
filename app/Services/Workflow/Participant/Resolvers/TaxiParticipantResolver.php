<?php

namespace App\Services\Workflow\Participant\Resolvers;

use App\Models\WorkflowInstance;
use App\Services\Workflow\Participant\ParticipantResolver;

class TaxiParticipantResolver implements ParticipantResolver
{
    public function resolve(WorkflowInstance $instance): array
    {
        $participants = [];

        // $document = $instance->document;
        // $taxi = $document->taxi;

        /*
        |--------------------------------------------------------------------------
        | 1. ACTEUR PRINCIPAL
        |--------------------------------------------------------------------------
        */

        // $participants[] = [
        //     'type' => 'PRIMARY_ACTOR',
        //     'label' => 'Bénéficiaire',
        //     'user_id' => $taxi->beneficiary_id,
        //     'name' => $taxi->beneficiary->name ?? null,
        // ];

        /*
        |--------------------------------------------------------------------------
        | 2. CHAÎNE DE WORKFLOW (ORDER + DECISION + SOURCE_VALUE)
        |--------------------------------------------------------------------------
        */

        $instance_steps = $instance
            ->instance_steps()
            ->with(["assignments"])
            ->orderBy("position")
            ->get();

        foreach ($instance_steps as $instance_step) {
            foreach ($instance_step->assignments as $assignment) {
                $isApproved = $assignment->decision === "APPROVED";

                $participants[] = [
                    /*
                    | rôle métier réel issu du workflow
                    */
                    "type" => $this->mapSourceValueToType(
                        $assignment->source_value
                    ),

                    /*
                    | label dynamique (important pour PDF)
                    */
                    "label" => $instance_step->name,

                    "validated_at" => $assignment->validated_at,

                    /*
                     * Règles de rendu
                     */
                    "signature_visibility" => $assignment->signature_visibility,
                    "signature_mode" => $assignment->signature_mode,

                    "paraph_visibility" => $assignment->paraph_visibility,
                    "paraph_mode" => $assignment->paraph_mode,

                    /*
                    | utilisateur réel qui a exécuté
                    */
                    "user_id" => $assignment->user_id,
                    "role_id" => $assignment->role_id,
                    "name" => $assignment->user->name ?? null,

                    /*
                    | état du workflow
                    */
                    "status" => $assignment->decision,

                    /*
            |--------------------------------------------------------------------------
            | SIGNATURE LOGIC
            |--------------------------------------------------------------------------
            */
                    "signed" => $isApproved,

                    /*
                    | traçabilité métier
                    */
                    "source_type" => $assignment->source_type,
                    "source_value" => $assignment->source_value,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. SIGNATAIRE FINAL (si existant)
        |--------------------------------------------------------------------------
        */

        if ($instance->final_signer_id) {
            $participants[] = [
                "type" => "SIGNER",
                "label" => "Signataire final",
                "user_id" => $instance->final_signer_id,
                "name" => $instance->finalSigner->name ?? null,
                "status" => "APPROVED",
            ];
        }

        return $participants;
    }

    private function mapSourceValueToType(string $value): string
    {
        $map = [
            "DIRECT_MANAGER" => "APPROVER",
            "HEAD_OF_DEPARTMENT" => "APPROVER",
            "SIGNATORY" => "SIGNER",
            "OWNER" => "PRIMARY_ACTOR",
        ];

        return $map[$value] ?? "APPROVER";
    }
}
