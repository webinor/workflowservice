<?php

namespace App\Services\Workflow\Event;

use App\Models\WorkflowActionStepEvent;
use App\Models\WorkflowEvent;
use App\Models\WorkflowEventAudience;
use App\Models\WorkflowInstanceStep;
use App\Services\User\UserServiceClient;
use App\Services\Department\DepartmentServiceClient;
use App\Services\Actor\ActorServiceClient;
use Exception;

class WorkflowAudienceResolver
{
    protected UserServiceClient $userService;
    protected ActorServiceClient $actorServiceClient;
    protected DepartmentServiceClient $departmentService;

    public function __construct(
        UserServiceClient $userService,
        ActorServiceClient $actorServiceClient,
        DepartmentServiceClient $departmentService
    ) {
        $this->userService = $userService;
        $this->departmentService = $departmentService;
        $this->actorServiceClient = $actorServiceClient;
    }

    /**
     * Résolution complète des audiences
     */
    public function resolve(WorkflowEvent $event, WorkflowInstanceStep $instance , array $document , array $context=[])//: array
    {
        $audiences = WorkflowEventAudience::where(
            'workflow_event_id',
            $event->id
        )
        ->where('active', true)
        ->get();

   


        $results = [];

        foreach ($audiences as $audience) {

            $recipients = [];

            switch ($audience->target_type) {

                /**
                 * ==========================
                 * ACTOR
                 * ==========================
                 */
                case 'ACTOR':

                    // $actor_details = $document['actor_details']['organization']['position'];
                    $actor_details = $document;
                    // $document[$document['document_type']['slug']]["actor_details"];
                    
                    // $document['organization']['position']['position']['user_id]
                    // throw new Exception(json_encode($actor_details), 1);
                    

                   $recipients = $this->resolveActor(
                        $audience->target_value,
                        $document["actor_id"],
                        $document["creator_employee_id"]
                    );

                    break;

                /**
                 * ==========================
                 * ROLE
                 * ==========================
                 */
                case 'ROLE':

                    $recipients = $this->resolveRole(
                        $audience->target_value
                    );

                    break;

                /**
                 * ==========================
                 * USER
                 * ==========================
                 */
                case 'USER':

                    $recipients = $this->resolveUser(
                        $audience->target_value
                    );

                    break;

                /**
                 * ==========================
                 * STEP_VALIDATOR
                 * ==========================
                 */
                case 'STEP_VALIDATOR':

                    $recipients = $this->resolveStepValidators(
                        $instance
                    );

                    break;

                /**
                 * ==========================
                 * SERVICE_HEAD
                 * ==========================
                 */
                case 'SERVICE_HEAD':

                    $recipients = $this->resolveServiceHead(
                        $audience->target_value
                    );

                    break;
            }

            // if (!empty($recipients)) {

            //     $results[] = [
            //         'channel' => $audience->channel,
            //         'recipients' => $recipients,
            //         'template_id' => $audience->notification_template_id,
            //     ];
            // }

            if (empty($recipients)) {
    continue;
}

$channel = $audience->channel;

$recipientType = strtolower(
    $audience->recipient_type ?? 'TO'
);

if (!isset($results[$channel])) {

    $results[$channel] = [
        'to' => [],
        'cc' => [],
        'bcc' => [],
    ];
}

$results[$channel][$recipientType] = array_merge(
    $results[$channel][$recipientType],
    $recipients
);
        }


        foreach ($results as $channel => $groups) {

    $results[$channel]['to'] = collect(
        $groups['to']
    )
    ->unique('recipient_email')
    ->values()
    ->toArray();

    $results[$channel]['cc'] = collect(
        $groups['cc']
    )
    ->unique('recipient_email')
    ->values()
    ->toArray();

    $results[$channel]['bcc'] = collect(
        $groups['bcc']
    )
    ->unique('recipient_email')
    ->values()
    ->toArray();
}

        return $results;
    }

    /**
     * =====================================
     * ACTORS
     * =====================================
     */
    private function resolveActor(
        string $actor,
        int $actor_id,
        int $owner_id
    )//: array 
    {

    // return  [$actor]; 

        switch ($actor) {

       

            /**
             * Missionnaire / propriétaire document
             */
            case "MISSION_EXECUTOR":

                // return ["ici"];

             $user = $this->actorServiceClient
                    ->findEmployee(
                        $actor_id
                        // $instance->created_by
                    );

                return $user
                    ? [$this->formatRecipient($user)]
                    : [];


            case "OWNER":

                // return ["ici"];
                //    throw new Exception(json_encode($actor_id), 1);


             $actor = $this->actorServiceClient
                    ->findEmployee(
                       $owner_id ? (int)$owner_id : (int)$actor_id
                    );

                //    throw new Exception(json_encode($actor), 1);
                    

                return $actor
                    ? [$this->formatRecipient($actor)]
                    : [];


            
            default:
                return [];
        }
    }

    /**
     * =====================================
     * ROLE
     * =====================================
     */
    private function resolveRole(
        string $roleCode
    ): array {

        $users = $this->userService
            ->usersByRole($roleCode);

        return collect($users)
            ->map(fn ($user) => $this->formatRecipient($user))
            ->toArray();
    }

    /**
     * =====================================
     * USER
     * =====================================
     */
    private function resolveUser(
        int $userId
    ): array {

        $user = $this->userService
            ->find($userId);

        return $user
            ? [$this->formatRecipient($user)]
            : [];
    }

    /**
     * =====================================
     * VALIDATEURS ÉTAPE COURANTE
     * =====================================
     */
    private function resolveStepValidators(
        $instance
    ): array {

        $step = $instance
            ->instance_steps()
            ->where('status', 'PENDING')
            ->first();

        if (!$step) {
            return [];
        }

        $roleIds = $step
            ->roles()
            ->pluck('role_id')
            ->toArray();

        $users = [];

        foreach ($roleIds as $roleId) {

            $roleUsers = $this->userService
                ->usersByRoleId($roleId);

            $users = array_merge(
                $users,
                $roleUsers
            );
        }

        return collect($users)
            ->unique('id')
            ->map(fn ($user) => $this->formatRecipient($user))
            ->values()
            ->toArray();
    }

    /**
     * =====================================
     * CHEF SERVICE
     * =====================================
     */
    private function resolveServiceHead(
        string $serviceCode
    ): array {

        $user = $this->departmentService
            ->serviceHead($serviceCode);

        return $user
            ? [$this->formatRecipient($user)]
            : [];
    }

    /**
     * =====================================
     * FORMAT STANDARD
     * =====================================
     */
    private function formatRecipient(
        array $actor
    ): array {

        return [
            'recipient_id' => $actor['id'] ?? null,
            'recipient_email' => $actor['email'] ?? null,
            'recipient_phone' => $actor['phone'] ?? null,
        ];
    }
}