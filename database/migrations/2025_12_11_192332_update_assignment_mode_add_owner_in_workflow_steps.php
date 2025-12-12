<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAssignmentModeAddOwnerInWorkflowSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->enum('assignment_mode', ['STATIC', 'DYNAMIC', 'OWNER'])
                ->default('STATIC')
                ->change();
        });
    }

    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->enum('assignment_mode', ['STATIC', 'DYNAMIC'])
                ->default('STATIC')
                ->change();
        });
    }
}
