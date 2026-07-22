<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequirementsToWorkflowActionStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_action_steps', function (Blueprint $table) {
            $table->json('requirements')
                ->nullable()
                ->after('transaction_type_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_action_steps', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });
    }
}