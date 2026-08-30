<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\WorkflowNotificationDataBuilder;
use Illuminate\Support\Facades\Log;

class RegularizationSheetRegularizedHandler
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
            '[WORKFLOW:REGULARIZATION_SHEET_REGULARIZED] ===== START =====',
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
        | Conservé pour rester cohérent avec les autres handlers
        | et permettre de récupérer ultérieurement des informations
        | spécifiques à l'étape.
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
        | Régularisateur
        |--------------------------------------------------------------------------
        |
        | L'acteur est celui qui vient d'effectuer la régularisation.
        |
        */

        $actorId =
            isset($config['validatorId'])
                ? (int) $config['validatorId']
                : null;

        if (!$actorId) {

            Log::warning(
                '[WORKFLOW:REGULARIZATION_SHEET_REGULARIZED] Aucun actorId fourni.',
                [
                    'document_uuid' => $documentUuid,
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
        | Régularisateur
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
                'regularized'
            );

        /*
        |--------------------------------------------------------------------------
        | Montant
        |--------------------------------------------------------------------------
        |
        | On récupère le montant depuis documentData.
        | Si ton builder/document utilise déjà une structure précise
        | pour le montant, on pourra adapter cette partie.
        |
        */

        $amount =
            $documentData['amount']
            ?? $documentData['actual_total']
            ?? $documentData['total_amount']
            ?? null;

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

                'regularized_by' =>
                    $actor['name']
                    ?? null,

                'regularized_by_email' =>
                    $actor['email']
                    ?? null,

                'regularized_at' =>
                    now()->toDateTimeString(),

                'amount' =>
                    $amount,

                'status' =>
                    'REGULARIZED',

                'message' =>
                    'La fiche de régularisation a été régularisée avec succès.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            '[WORKFLOW:REGULARIZATION_SHEET_REGULARIZED] Final notification data',
            [
                'document_id' =>
                    $documentUuid,

                'document_type' =>
                    $notificationData['document_type']
                    ?? null,

                'owner_email' =>
                    $notificationData['owner_email'],

                'regularized_by' =>
                    $notificationData['regularized_by'],

                'regularized_by_email' =>
                    $notificationData['regularized_by_email'],

                'regularized_at' =>
                    $notificationData['regularized_at'],

                'amount' =>
                    $notificationData['amount'],
            ]
        );

        Log::info(
            '[WORKFLOW:REGULARIZATION_SHEET_REGULARIZED] ===== END =====',
            [
                'document_uuid' =>
                    $documentUuid,

                'actorId' =>
                    $actorId,
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