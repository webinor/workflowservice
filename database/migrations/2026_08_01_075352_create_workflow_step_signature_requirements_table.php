<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;



return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_signature_requirements', function (Blueprint $table) {

            $table->id();


            /**
             * Action étape workflow concernée
             */
            $table->foreignId('workflow_step_id')
                ->constrained('workflow_steps')
                ->cascadeOnDelete() 
                 ->name('wassr_step_fk');;


            /**
             * Type de signature attendu
             *
             * Ex:
             * RECEIPT
             * SUPPLIER_INVOICE
             * CONTRACT
             * ATTACHMENT
             */
            $table->string('signature_type',100);

            /**
             * Signature obligatoire
             */
            $table->boolean('is_required')
                ->default(true);



            /**
             * Ordre d'affichage
             */
            $table->unsignedInteger('display_order')
                ->default(0);



            $table->timestamps();



            $table->unique([
                'workflow_step_id',
                'signature_type'
            ],'wsp_unique_requirement');

        });
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_step_signature_requirements'
        );
    }
};
