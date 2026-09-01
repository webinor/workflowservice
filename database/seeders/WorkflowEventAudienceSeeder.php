<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowEvent;
use App\Models\WorkflowEventAudience;

class WorkflowEventAudienceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $audiences = [

            [
                'event' => 'DOCUMENT_RETURNED',
                'target_type' => 'ACTOR',
                'target_value' => 'OWNER',
                'channel' => 'EMAIL',
                'recipient_type' => 'TO',
                'notification_template_id' => null,
            ],

            [
                'event' => 'DOCUMENT_REJECTED',
                'target_type' => 'ACTOR',
                'target_value' => 'OWNER',
                'channel' => 'EMAIL',
                'recipient_type' => 'TO',
                'notification_template_id' => null,
            ],

            [
                'event' => 'DOCUMENT_VALIDATED',
                'target_type' => 'ACTOR',
                'target_value' => 'OWNER',
                'channel' => 'EMAIL',
                'recipient_type' => 'TO',
                'notification_template_id' => null,
            ],

            [
                'event' => 'DOCUMENT_WORKFLOW_COMPLETED',
                'target_type' => 'ACTOR',
                'target_value' => 'OWNER',
                'channel' => 'EMAIL',
                'recipient_type' => 'TO',
                'notification_template_id' => null,
            ],


            /*
|--------------------------------------------------------------------------
| Documents signés
|--------------------------------------------------------------------------
*/

[
    'event' => 'REGULARIZATION_SHEET_SIGNED',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],


[
    'event' => 'REGULARIZATION_SHEET_READY_TO_FINALIZE',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],

[
    'event' => 'TAXI_PAPER_SIGNED',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],

[
    'event' => 'FEE_NOTE_SIGNED',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],

[
    'event' => 'REGULARIZATION_SHEET_REGULARIZED',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],



/*
|--------------------------------------------------------------------------
| Demande de congé approuvée
|--------------------------------------------------------------------------
*/

[
    'event' => 'LEAVE_REQUEST_APPROVED',
    'target_type' => 'ACTOR',
    'target_value' => 'OWNER',
    'channel' => 'EMAIL',
    'recipient_type' => 'TO',
    'notification_template_id' => null,
],

        ];

        foreach ($audiences as $audience) {

            $event = WorkflowEvent::where(
                'code',
                $audience['event']
            )->first();

            if (!$event) {
                continue;
            }

            WorkflowEventAudience::updateOrCreate(
                [
                    'workflow_event_id' => $event->id,
                    'target_type' => $audience['target_type'],
                    'target_value' => $audience['target_value'],
                    'channel' => $audience['channel'],
                    'recipient_type' => $audience['recipient_type'],
                ],
                [
                    'notification_template_id' =>
                        $audience['notification_template_id'],
                ]
            );
        }
    }
}