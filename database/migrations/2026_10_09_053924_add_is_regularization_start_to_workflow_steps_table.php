<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsRegularizationStartToWorkflowStepsTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->boolean('is_regularization_start')
                ->default(false)
                ->after('is_archived_step');
        });
    }

    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('is_regularization_start');
        });
    }
}