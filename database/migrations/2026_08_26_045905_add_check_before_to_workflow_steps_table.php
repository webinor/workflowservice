<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCheckBeforeToWorkflowStepsTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->boolean('check_before')
                ->default(true)
                ->after('is_bypassable');
        });
    }

    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('check_before');
        });
    }
}