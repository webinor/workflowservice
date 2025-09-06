<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleIdToWorkflowInstanceStep extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('role_id')->nullable()->after('user_id'); // utilisateur ayant agi
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
            $table->dropColumn('role_id');
        });
    }
}
