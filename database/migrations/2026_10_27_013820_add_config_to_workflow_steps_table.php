<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfigToWorkflowStepsTable extends Migration
{
    /**
     * Exécute la migration.
     *
     * Ajoute une colonne JSON permettant de configurer
     * les actions autorisées pour chaque étape du workflow.
     *
     * Exemple :
     * {
     *     "authorized_actions": [
     *         "define_purchase_type"
     *     ]
     * }
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->json('config')->after('check_before')->nullable();
        });
    }

    /**
     * Annule la migration.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
}