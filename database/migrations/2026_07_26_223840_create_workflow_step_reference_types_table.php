<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowStepReferenceTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('workflow_step_reference_types', function (Blueprint $table) {

            $table->id();

            $table->foreignId('workflow_step_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedMediumInteger('document_reference_type_id');

            $table->string('reference_type_code');

            // La référence est-elle obligatoire à cette étape ?
            $table->boolean('is_required')
                ->default(true);

            // Ordre d'affichage
            $table->unsignedInteger('display_order')
                ->default(0);

            $table->timestamps();

            // Empêche d'ajouter deux fois le même type de référence
            $table->unique([
                'workflow_step_id',
                'document_reference_type_id'
            ], 'workflow_step_reference_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('workflow_step_reference_types');
    }
}