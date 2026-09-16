<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Carbon\Carbon;
use Exception;
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

                'validatorId' =>
                    $config['validatorId'] ?? null,
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
        | Acteur
        |--------------------------------------------------------------------------
        */

        $validatorId =
            isset($config['validatorId'])
                ? (int) $config['validatorId']
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

        $validator =
            $this->builder->buildValidator(
                $validatorId
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

        $data = $data = [
    'subject' =>
        $subject,

    'owner_name' =>
        $owner['name']
        ?? null,

    'owner_email' =>
        $owner['email']
        ?? null,

    'approved_by' =>
        $validator['name']
        ?? null,

    'approved_by_email' =>
        $actor['email']
        ?? null,

    'approved_at' =>
        Carbon::parse($instance->executed_at)->format('d/m/Y')  ??  now()->toDateTimeString(),

    'status' =>
        'APPROVED',

    'message' =>
        'Votre demande de congé a été approuvée.',

    'leave_type' =>
        data_get(
            $documentData,
            'absence_request.leave_type.name'
        ),

    'start_date' =>
        data_get(
            $documentData,
            'absence_request.departure_date'
        ),

    'end_date' =>
        data_get(
            $documentData,
            'absence_request.return_date'
        ),
];


            // throw new Exception(json_encode( Carbon::parse($instance->executed_at)->format('d/m/Y') ), 1);
            

        $notificationData = array_merge(
            $document,
            $data
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