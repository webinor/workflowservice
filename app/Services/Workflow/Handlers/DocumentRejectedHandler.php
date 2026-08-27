<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowHistoryResolver;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Exception;
use Illuminate\Support\Facades\Log;

class DocumentRejectedHandler
    implements WorkflowEventHandlerInterface
{
    protected WorkflowNotificationDataBuilder $builder;

    protected WorkflowHistoryResolver $historyResolver;

    public function __construct(
        WorkflowNotificationDataBuilder $builder,
        WorkflowHistoryResolver $historyResolver
    ) {
        $this->builder = $builder;
        $this->historyResolver = $historyResolver;
    }

    public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

            // throw new Exception(json_encode($config), 1);


        Log::info(
            '[WORKFLOW:REJECTED] ===== START =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'instance_id' =>
                    $instance->id ?? null,

                'actor_id' =>
                    $config['actor_id']
                    ?? null,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Workflow instance
        |--------------------------------------------------------------------------
        */

        $workflowInstance =
            $instance instanceof WorkflowInstance
                ? $instance
                : (
                    isset($instance->workflowInstance)
                        && $instance->workflowInstance
                            instanceof WorkflowInstance
                        ? $instance->workflowInstance
                        : null
                );


        if (!$workflowInstance) {

            throw new \RuntimeException(
                'Unable to resolve WorkflowInstance.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Workflow step
        |--------------------------------------------------------------------------
        */

        $workflowStep =
            $instance instanceof WorkflowInstanceStep
                ? $instance
                : (
                    isset($instance->workflowInstanceStep)
                        && $instance->workflowInstanceStep
                            instanceof WorkflowInstanceStep
                        ? $instance->workflowInstanceStep
                        : null
                );


        /*
        |--------------------------------------------------------------------------
        | History
        |--------------------------------------------------------------------------
        */

        $history =
            $this->historyResolver->latestForModels(
                array_filter([
                    $workflowStep,
                    $workflowInstance,
                ]),
                'REJECTED'
            );


        /*
        |--------------------------------------------------------------------------
        | Actor
        |--------------------------------------------------------------------------
        */

        $actorId =
            isset($config['actor_id'])
                ? (int) $config['actor_id']
                : null;


        if (
            !$actorId &&
            $history &&
            $history->changed_by
        ) {

            $actorId =
                (int) $history->changed_by;
        }


        /*
        |--------------------------------------------------------------------------
        | Owner
        |--------------------------------------------------------------------------
        */

        $owner =
            $this->builder->buildOwner(
                $documentData
            );


        /*
        |--------------------------------------------------------------------------
        | Actor
        |--------------------------------------------------------------------------
        */

        $actor =
            $this->builder->buildActor(
                $actorId
            );


        /*
        |--------------------------------------------------------------------------
        | Document
        |--------------------------------------------------------------------------
        */

        $document =
            $this->builder->buildDocument(
                $documentUuid,
                $documentData
            );


        /*
        |--------------------------------------------------------------------------
        | Subject
        |--------------------------------------------------------------------------
        */

        $subject =
            $this->builder->getSubject(
                $documentData,
                'rejected'
            );


        /*
        |--------------------------------------------------------------------------
        | Notification
        |--------------------------------------------------------------------------
        */

        $notificationData = array_merge(
            $document,
            [

                'subject' =>
                    $subject,

                'owner_name' =>
                    $owner['name'],

                'owner_email' =>
                    $owner['email'],

                'rejected_by' =>
                    $actor['name'],

                'rejected_by_email' =>
                    $actor['email'],

                'rejected_at' =>
                    $this->builder->formatHistoryDate(
                        $history
                    ),

                'reason' =>
                    $this->builder->getHistoryComment(
                        $history
                    ),

                'status' =>
                    'REJECTED',

                'message' =>
                    'Votre document a été rejeté.',
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:REJECTED] Final notification data',
            [
                'document_id' =>
                    $documentUuid,

                'document_type' =>
                    $notificationData['document_type'],

                'owner_email' =>
                    $notificationData['owner_email'],

                'rejected_by' =>
                    $notificationData['rejected_by'],

                'rejected_by_email' =>
                    $notificationData['rejected_by_email'],

                'rejected_at' =>
                    $notificationData['rejected_at'],

                'reason' =>
                    $notificationData['reason'],

                'history_id' =>
                    $history->id
                    ?? null,
            ]
        );


        Log::info(
            '[WORKFLOW:REJECTED] ===== END =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'history_id' =>
                    $history->id
                    ?? null,
            ]
        );


        return [

            'data' =>
                $notificationData,

            'attachments' =>
                [],
        ];
    }
}