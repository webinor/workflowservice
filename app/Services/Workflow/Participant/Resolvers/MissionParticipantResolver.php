<?php

namespace App\Services\Workflow\Participant\Resolvers;


use App\Models\WorkflowInstance;
use App\Services\Workflow\Participant\ParticipantResolver;


class MissionParticipantResolver implements ParticipantResolver
{
    public function resolve(WorkflowInstance $instance): array
    {
        $participants = [];

        $document = $instance->document;

        // 1. Agent en mission
        $participants[] = [
            'type' => 'PRIMARY_ACTOR',
            'label' => 'Agent en mission',
            'user_id' => $document->mission->actor_id,
            'name' => $document->mission->actor->name ?? null,
        ];

        // 2. Chaîne de validation dynamique
        foreach ($instance->steps as $step) {
            foreach ($step->assignments as $assignment) {

                $participants[] = [
                    'type' => 'APPROVER',
                    'label' => $step->name,
                    'user_id' => $assignment->user_id,
                    'name' => $assignment->user->name ?? null,
                ];
            }
        }

        return $participants;
    }
}