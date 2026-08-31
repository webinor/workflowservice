<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Illuminate\Support\Facades\Log;

class LeaveRequestApprovedHandler
    implements WorkflowEventHandlerInterface
{
    protected WorkflowNotificationDataBuilder $builder;

    public function __construct(
        WorkflowNotificationDataBuilder $builder
    ) {
        $this->builder = $builder;
    }

    public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        Log::info(
            '[WORKFLOW:LEAVE_REQUEST_APPROVED] ===== START =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'instance_id' =>
                    $instance->id ?? null,

                'actorId' =>
                    $config['actorId'] ?? null,
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
        |
        | Conservé pour rester compatible avec l'architecture
        | actuelle des handlers et permettre une évolution future.
        |
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
        | Acteur
        |--------------------------------------------------------------------------
        */

        $actorId =
            isset($config['actorId'])
                ? (int) $config['actorId']
                : null;

        /*
        |--------------------------------------------------------------------------
        | Propriétaire de la demande
        |--------------------------------------------------------------------------
        */

        $owner =
            $this->builder->buildOwner(
                $documentData
            );

        /*
        |--------------------------------------------------------------------------
        | Acteur ayant approuvé
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
                $documentData,
                $config
            );

        /*
        |--------------------------------------------------------------------------
        | Subject
        |--------------------------------------------------------------------------
        */

        $subject =
            $this->builder->getSubject(
                $documentData,
                'approved'
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
                    $owner['name']
                    ?? null,

                'owner_email' =>
                    $owner['email']
                    ?? null,

                'approved_by' =>
                    $actor['name']
                    ?? null,

                'approved_by_email' =>
                    $actor['email']
                    ?? null,

                'approved_at' =>
                    now()->toDateTimeString(),

                'status' =>
                    'APPROVED',

                'message' =>
                    'Votre demande de congé a été approuvée.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:LEAVE_REQUEST_APPROVED] Final notification data',
            [
                'document_uuid' =>
                    $documentUuid,

                'document_type' =>
                    $notificationData['document_type']
                    ?? null,

                'owner_email' =>
                    $notificationData['owner_email']
                    ?? null,

                'approved_by' =>
                    $notificationData['approved_by']
                    ?? null,

                'approved_by_email' =>
                    $notificationData['approved_by_email']
                    ?? null,

                'approved_at' =>
                    $notificationData['approved_at']
                    ?? null,
            ]
        );

        Log::info(
            '[WORKFLOW:LEAVE_REQUEST_APPROVED] ===== END =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'actorId' =>
                    $actorId,
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