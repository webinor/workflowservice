<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryModeToWorkflowActionStepEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_action_step_events', function (Blueprint $table) {
             
            $table->enum('delivery_mode', [
                'GROUPED',
                'INDIVIDUAL'
            ])
            ->default('INDIVIDUAL')
            ->after('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_action_step_events', function (Blueprint $table) {
             $table->dropColumn('delivery_mode');
        });
    }
}
