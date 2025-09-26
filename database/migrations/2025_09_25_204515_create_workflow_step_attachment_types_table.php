<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowStepAttachmentTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::create('workflow_step_attachment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')
                ->constrained('workflow_steps')
                ->onDelete('cascade');

            // Référence logique vers l'ID d'un attachment_type du microservice document
            $table->unsignedBigInteger('attachment_type_id');

            $table->timestamps();

            // Empêche les doublons (une étape ne peut pas avoir deux fois le même type)
   // Nom explicite et court pour l'index unique
    $table->unique(['workflow_step_id', 'attachment_type_id'], 'step_attachment_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_step_attachment_types');
    }
}
