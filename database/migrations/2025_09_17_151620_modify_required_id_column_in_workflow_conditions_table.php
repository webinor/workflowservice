<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyRequiredIdColumnInWorkflowConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
             // Convertir required_id en JSON nullable
            $table->json('required_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
               // Revenir à l'ancienne colonne (par ex. unsignedBigInteger)
            $table->unsignedBigInteger('required_id')->nullable()->change();
        });
    }
}
