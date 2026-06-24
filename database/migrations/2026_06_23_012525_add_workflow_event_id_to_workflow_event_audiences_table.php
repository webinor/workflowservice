<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkflowEventIdToWorkflowEventAudiencesTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_event_audiences', function (Blueprint $table) {

            $table->foreignId('workflow_event_id')
                ->nullable()
                ->after('id')
                ->constrained('workflow_events')
                ->cascadeOnDelete();

        });
    }

    public function down()
    {
        Schema::table('workflow_event_audiences', function (Blueprint $table) {

            $table->dropForeign(['workflow_event_id']);
            $table->dropColumn('workflow_event_id');

        });
    }
}