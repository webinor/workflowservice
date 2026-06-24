<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventIdToWorkflowActionStepEventsTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_action_step_events', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->after('workflow_action_step_id')
                ->constrained('workflow_events')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('workflow_action_step_events', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
}