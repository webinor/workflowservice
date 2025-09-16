<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActionLabelToWorkflowActionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {
            $table->string('action_label', 50)
                  ->after('name') // optionnel : place la colonne après `name`
                  ->nullable()
                  ->comment('Version courte ou label de l’action, ex: "reconnue", "validée"');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {
            $table->dropColumn('action_label');
        });
    }
}
