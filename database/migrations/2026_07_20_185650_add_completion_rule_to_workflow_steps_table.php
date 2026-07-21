<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletionRuleToWorkflowStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {

            $table->string('completion_rule')
                ->default('ANY')
                ->after('name');

            $table->json('completion_rule_config')
                ->nullable()
                ->after('completion_rule');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {

            $table->dropColumn([
                'completion_rule',
                'completion_rule_config',
            ]);

        });
    }
}