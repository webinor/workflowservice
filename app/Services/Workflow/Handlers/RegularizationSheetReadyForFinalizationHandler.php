<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Illuminate\Support\Facades\Log;

class RegularizationSheetReadyForFinalizationHandler
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
            '[WORKFLOW:REGULARIZATION_SHEET_READY_FOR_FINALIZATION] ===== START =====',
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
        | Owner
        |--------------------------------------------------------------------------
        */

        $owner =
            $this->builder->buildOwner(
                $documentData
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
                'ready_for_finalization'
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

                'ready_at' =>
                    now()->toDateTimeString(),

                'status' =>
                    'READY_FOR_FINALIZATION',

                'message' =>
                    'Les pièces justificatives ont été signées. La fiche de régularisation est prête à être finalisée.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:REGULARIZATION_SHEET_READY_FOR_FINALIZATION] Final notification data',
            [
                'document_id' =>
                    $documentUuid,

                'document_type' =>
                    $notificationData['document_type']
                    ?? null,

                'owner_email' =>
                    $notificationData['owner_email'],

                'ready_at' =>
                    $notificationData['ready_at'],

                'status' =>
                    $notificationData['status'],
            ]
        );

        Log::info(
            '[WORKFLOW:REGULARIZATION_SHEET_READY_FOR_FINALIZATION] ===== END =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'actorId' =>
                    $config['actorId']
                    ?? null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return [

            'data' =>
                $notificationData,

            'attachments' =>
                [],
        ];
    }
}