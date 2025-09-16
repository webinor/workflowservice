<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExecutedAtToWorkflowInstanceStep extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->dateTime('executed_at')->after('role_id')->nullable();
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
            $table->dropColumn('executed_at');
        });
    }
}
