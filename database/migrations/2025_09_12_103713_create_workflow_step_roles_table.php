<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowStepRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('workflow_step_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')
                  ->constrained('workflow_steps')
                  ->onDelete('cascade'); // si une étape est supprimée, ses rôles aussi
            $table->unsignedInteger('role_id');
                 // ->constrained('roles')
                 // ->onDelete('cascade'); // si un rôle disparaît, il est supprimé ici aussi
            $table->timestamps();

            $table->unique(['workflow_step_id', 'role_id'], 'step_role_unique'); 
            // évite les doublons (même rôle plusieurs fois sur la même étape)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_step_roles');
    }
}
