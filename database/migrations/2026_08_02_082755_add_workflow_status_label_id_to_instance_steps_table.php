<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkflowStatusLabelIdToInstanceStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->foreignId('workflow_status_label_id')
                ->nullable()
                ->after('status')
                ->constrained('workflow_status_labels')
                ->nullOnDelete();
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
            $table->dropForeign(['workflow_status_label_id']);
            $table->dropColumn('workflow_status_label_id');
        });
    }
}