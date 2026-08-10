<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameValidatedAtToDecidedAtOnWorkflowInstanceStepAssignmentsTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->renameColumn('validated_at', 'decided_at');
        });
    }

    public function down()
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->renameColumn('decided_at', 'validated_at');
        });
    }
}