<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->unsignedBigInteger('workflow_status_label_id')
                ->nullable()
                ->after('status')
                ->comment("Label actuel du statut de l'instance de workflow");

            // clé étrangère (si la table existe)
            $table->foreign('workflow_status_label_id')
                ->references('id')
                ->on('workflow_status_labels')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->dropForeign(['workflow_status_label_id']);
            $table->dropColumn('workflow_status_label_id');

        });
    }
};