<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusTypeToWorkflowStatusLabelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_status_labels', function (Blueprint $table) {
            $table->string('status_type')->after('is_configurable')
                  ->comment("Indique si c'est un type commun a tous les type de docs ou bien pour un type precis");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_status_labels', function (Blueprint $table) {
            $table->dropColumn('status_type');
        });
    }
}
