<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowEvent;
use App\Models\WorkflowHandler;
use App\Services\Workflow\Handlers\GenerateMissionDocumentsHandler;
use App\Services\Workflow\Handlers\Leave\DeductLeaveDaysHandler;

class WorkflowHandlerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {

        $handlers = [

            [
                'event' => 'GENERATE_MISSION_DOCUMENTS',
                'handler' => GenerateMissionDocumentsHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

            [
                'event' => 'DEDUCT_LEAVE_DAYS',
                'handler' => DeductLeaveDaysHandler::class,
                'priority' => 1,
                'is_async' => false,
            ],

        ];


        foreach ($handlers as $item) {

            $event = WorkflowEvent::where('code', $item['event'])->first();

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
                    'enabled' => true,
                ]

            );

        }

    }
}