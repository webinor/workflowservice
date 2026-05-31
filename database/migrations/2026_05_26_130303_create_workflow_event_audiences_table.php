<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowEventAudiencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_event_audiences', function (Blueprint $table) {

            $table->id();

            /**
             * Événement workflow
             */
            $table->foreignId('workflow_action_step_event_id')
                ->constrained()
                ->onDelete('cascade');

            /**
             * Type de cible
             *
             * ROLE
             * USER
             * ACTOR
             * STEP_VALIDATOR
             * SERVICE_HEAD
             * DYNAMIC
             */
            $table->enum('target_type', [
                'ROLE',
                'USER',
                'ACTOR',
                'STEP_VALIDATOR',
                'SERVICE_HEAD',
                'DYNAMIC',
            ]);

            /**
             * Valeur associée
             *
             * Exemples :
             * - TREASURER
             * - 15
             * - MISSION_OWNER
             * - LOGISTICS
             * - mission.supervisor
             */
            $table->string('target_value');

            /**
             * Canal notification
             */
            $table->enum('channel', [
                'EMAIL',
                'SMS',
                'IN_APP',
                'PUSH',
            ])->default('EMAIL');

            /**
             * Template optionnel
             */
            $table->unsignedSmallInteger('notification_template_id')
                ->nullable();
                // ->constrained('notification_templates')
                // ->nullOnDelete();

            /**
             * Actif / inactif
             */
            $table->boolean('active')
                ->default(true);

            /**
             * Métadonnées
             */
            $table->json('metadata')
                ->nullable();

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
        Schema::dropIfExists(
            'workflow_event_audiences'
        );
    }
}