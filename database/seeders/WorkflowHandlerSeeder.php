<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowEvent;
use App\Models\WorkflowHandler;

use App\Services\Workflow\Handlers\DeductLeaveDaysHandler;
use App\Services\Workflow\Handlers\GenerateLeaveDocumentsHandler;
use App\Services\Workflow\Handlers\GenerateMissionDocumentsHandler;
use App\Services\Workflow\Handlers\NotifyTaxiPaperBeneficiaryHandler;

use App\Services\Workflow\Handlers\DocumentReturnedHandler;
use App\Services\Workflow\Handlers\DocumentRejectedHandler;
use App\Services\Workflow\Handlers\DocumentValidatedHandler;
use App\Services\Workflow\Handlers\DocumentWorkflowCompletedHandler;

class WorkflowHandlerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $handlers = [

            /*
            |--------------------------------------------------------------------------
            | Mission
            |--------------------------------------------------------------------------
            */

            [
                'event' => 'GENERATE_MISSION_DOCUMENTS',
                'handler' => GenerateMissionDocumentsHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Congés
            |--------------------------------------------------------------------------
            */

            [
                'event' => 'DEDUCT_LEAVE_DAYS',
                'handler' => DeductLeaveDaysHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            [
                'event' => 'GENERATE_LEAVE_DOCUMENTS',
                'handler' => GenerateLeaveDocumentsHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Papier taxi
            |--------------------------------------------------------------------------
            */

            [
                'event' => 'NOTIFY_TAXI_PAPER_BENEFICIARY',
                'handler' => NotifyTaxiPaperBeneficiaryHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Workflow documentaire
            |--------------------------------------------------------------------------
            */

            [
                'event' => 'DOCUMENT_RETURNED',
                'handler' => DocumentReturnedHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            [
                'event' => 'DOCUMENT_REJECTED',
                'handler' => DocumentRejectedHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            [
                'event' => 'DOCUMENT_VALIDATED',
                'handler' => DocumentValidatedHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            [
                'event' => 'DOCUMENT_WORKFLOW_COMPLETED',
                'handler' => DocumentWorkflowCompletedHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

        ];

        foreach ($handlers as $item) {

            $event = WorkflowEvent::where(
                'code',
                $item['event']
            )->first();

            if (!$event) {
                continue;
            }

            WorkflowHandler::updateOrCreate(
                [
                    'workflow_event_id' => $event->id,
                    'handler_class' => $item['handler'],
                ],
                [
                    'priority' => $item['priority'],
                    'is_async' => $item['is_async'],
                    // 'enabled' => true,
                ]
            );
        }
    }
}