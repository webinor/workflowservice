<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Illuminate\Support\Facades\Log;

class TaxiPaperSignedHandler
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
            '[WORKFLOW:TAXI_PAPER_SIGNED] ===== START =====',
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
        | Signataire
        |--------------------------------------------------------------------------
        */

        $actorId =
            isset($config['actorId'])
                ? (int) $config['actorId']
                : null;

        if (!$actorId) {

            Log::warning(
                '[WORKFLOW:TAXI_PAPER_SIGNED] Aucun actorId fourni.',
                [
                    'document_uuid' =>
                        $documentUuid,
                ]
            );
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
        | Signataire
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
                'signed'
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

                'signed_by' =>
                    $actor['name']
                    ?? null,

                'signed_by_email' =>
                    $actor['email']
                    ?? null,

                'signed_at' =>
                    now()->toDateTimeString(),

                'status' =>
                    'SIGNED',

                'message' =>
                    'Le papier taxi a été signé et peut poursuivre son processus de traitement.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:TAXI_PAPER_SIGNED] Final notification data',
            [
                'document_id' =>
                    $documentUuid,

                'document_type' =>
                    $notificationData['document_type']
                    ?? null,

                'owner_email' =>
                    $notificationData['owner_email'],

                'signed_by' =>
                    $notificationData['signed_by'],

                'signed_by_email' =>
                    $notificationData['signed_by_email'],

                'signed_at' =>
                    $notificationData['signed_at'],
            ]
        );

        Log::info(
            '[WORKFLOW:TAXI_PAPER_SIGNED] ===== END =====',
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