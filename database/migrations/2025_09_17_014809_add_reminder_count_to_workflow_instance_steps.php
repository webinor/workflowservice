<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReminderCountToWorkflowInstanceSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
                        $table->unsignedInteger('reminder_count')->default(0)->after('due_date');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
                        $table->dropColumn('reminder_count');

        });
    }
}
