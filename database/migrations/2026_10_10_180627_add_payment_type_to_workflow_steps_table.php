<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentTypeToWorkflowStepsTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('payment_type', 191)
            ->after('is_payment_step')
                ->nullable();
        });
    }

    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
}