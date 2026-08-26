<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowHistoryResolver;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Illuminate\Support\Facades\Log;

class DocumentReturnedHandler
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

        Log::info(
            '[WORKFLOW:RETURNED] ===== START =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'instance_id' =>
                    $instance->id ?? null,

                'instance_class' =>
                    is_object($instance)
                        ? get_class($instance)
                        : null,

                'validator_id' =>
                    $config['validator_id']
                    ?? null,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Normalisation de l'instance
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

            Log::error(
                '[WORKFLOW:RETURNED] Workflow instance not found',
                [
                    'instance_class' =>
                        is_object($instance)
                            ? get_class($instance)
                            : null,
                ]
            );

            throw new \RuntimeException(
                'Unable to resolve WorkflowInstance.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Résolution du WorkflowInstanceStep
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
        | Résolution de l'historique
        |--------------------------------------------------------------------------
        |
        | Priorité :
        |
        | 1. WorkflowInstanceStep
        | 2. WorkflowInstance
        |
        */

        $history =
            $this->historyResolver->latestForModels(
                array_filter([
                    $workflowStep,
                    $workflowInstance,
                ]),
                'RETURNED_FOR_MODIFICATION'
            );


        /*
        |--------------------------------------------------------------------------
        | Résolution de l'acteur
        |--------------------------------------------------------------------------
        |
        | Priorité :
        |
        | 1. validator_id fourni par le workflow
        | 2. changed_by de l'historique
        |
        */

        $actorId =
            isset($config['validator_id'])
                ? (int) $config['validator_id']
                : null;


        if (
            !$actorId &&
            $history &&
            $history->changed_by
        ) {

            $actorId =
                (int) $history->changed_by;
        }


        Log::info(
            '[WORKFLOW:RETURNED] History resolved',
            [
                'workflow_instance_id' =>
                    $workflowInstance->id,

                'workflow_step_id' =>
                    $workflowStep->id
                    ?? null,

                'history_found' =>
                    !is_null($history),

                'history_id' =>
                    $history->id
                    ?? null,

                'history_model_id' =>
                    $history->model_id
                    ?? null,

                'history_model_type' =>
                    $history->model_type
                    ?? null,

                'changed_by' =>
                    $history->changed_by
                    ?? null,

                'validator_id' =>
                    $actorId,
            ]
        );


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


        Log::info(
            '[WORKFLOW:RETURNED] Actor resolved',
            [
                'validator_id' =>
                    $actor['id'],

                'actor_name' =>
                    $actor['name'],

                'actor_email' =>
                    $actor['email'],

                'actor_found' =>
                    !is_null($actor['user']),
            ]
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
                'returned'
            );


        /*
        |--------------------------------------------------------------------------
        | Notification data
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

                'returned_by' =>
                    $actor['name'],

                'returned_by_email' =>
                    $actor['email'],

                'returned_at' =>
                    $this->builder->formatHistoryDate(
                        $history
                    ),

                'reason' =>
                    $this->builder->getHistoryComment(
                        $history
                    ),

                'status' =>
                    'RETURNED_FOR_MODIFICATION',

                'message' =>
                    'Votre document a été retourné pour modification.',
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:RETURNED] Final notification data',
            [
                'document_id' =>
                    $notificationData['document_id'],

                'document_type' =>
                    $notificationData['document_type'],

                'document_title' =>
                    $notificationData['document_title'],

                'document_reference' =>
                    $notificationData['document_reference'],

                'owner_name' =>
                    $notificationData['owner_name'],

                'owner_email' =>
                    $notificationData['owner_email'],

                'returned_by' =>
                    $notificationData['returned_by'],

                'returned_by_email' =>
                    $notificationData['returned_by_email'],

                'returned_at' =>
                    $notificationData['returned_at'],

                'reason' =>
                    $notificationData['reason'],

                'history_id' =>
                    $history->id
                    ?? null,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Vérifications métier
        |--------------------------------------------------------------------------
        */

        if (empty($owner['email'])) {

            Log::warning(
                '[WORKFLOW:RETURNED] Owner email is empty',
                [
                    'document_id' =>
                        $documentUuid,

                    'owner_id' =>
                        $owner['id'],
                ]
            );
        }


        if (
            $actorId &&
            !$actor['user']
        ) {

            Log::warning(
                '[WORKFLOW:RETURNED] Actor could not be resolved',
                [
                    'validator_id' =>
                        $actorId,

                    'history_id' =>
                        $history->id
                        ?? null,
                ]
            );
        }


        if (!$history) {

            Log::warning(
                '[WORKFLOW:RETURNED] No return history found',
                [
                    'workflow_instance_id' =>
                        $workflowInstance->id,

                    'workflow_step_id' =>
                        $workflowStep->id
                        ?? null,
                ]
            );
        }


        Log::info(
            '[WORKFLOW:RETURNED] ===== END =====',
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