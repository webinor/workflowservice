<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_conditions', function (Blueprint $table) {
            $table->id();

            // Étape source (celle qui définit la condition)
            $table->foreignId('workflow_step_id')
                  ->constrained('workflow_steps')
                  ->onDelete('cascade');

            // Étape cible (si la condition est remplie, on passe à cette étape)
            $table->foreignId('next_step_id')
                  ->nullable()
                  ->constrained('workflow_steps')
                  ->nullOnDelete();

            // Type de condition (ex: field, role, montant, etc.)
            $table->string('condition_type');

            // Expression ou valeur de la condition
            $table->string('operator')->nullable(); // '=', '!=', '>', '<', 'IN', etc.
            $table->string('field')->nullable();    // champ sur lequel la condition s’applique
            $table->string('value')->nullable();    // valeur attendue

            // Pour stocker une logique complexe (JSON)
            $table->json('metadata')->nullable();

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
        Schema::dropIfExists('workflow_conditions');
    }
}
