<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterWorkflowActionStepsAddTransactionTypeCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_action_steps', function (Blueprint $table) {

            $table->string('transaction_type_code')
                ->nullable()
                ->after('permission_required');

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

            $table->dropColumn('transaction_type_code');

        });
    }
}