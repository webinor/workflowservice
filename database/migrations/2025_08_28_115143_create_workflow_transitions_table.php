<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowTransitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();

            // Workflow concerné
            $table->foreignId('workflow_id')
                  ->constrained('workflows')
                  ->onDelete('cascade');

            // Étape source
            $table->foreignId('from_step_id')
                  ->constrained('workflow_steps')
                  ->onDelete('cascade');

            // Étape cible
            $table->foreignId('to_step_id')
                  ->constrained('workflow_steps')
                  ->onDelete('cascade');

            // Libellé de la transition (ex: "Valider", "Rejeter")
            $table->string('name');

            // Type de transition (linéaire, conditionnelle, parallèle, rejet, retour au demandeur, etc.)
            $table->string('type')->default('linear');

            // Règles supplémentaires (JSON si besoin)
            $table->json('rules')->nullable();

            // Optionnel : condition liée (clé étrangère vers workflow_conditions)
            $table->foreignId('condition_id')
                  ->nullable()
                  ->constrained('workflow_conditions')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_transitions');
    }
}
