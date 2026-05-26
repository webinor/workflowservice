<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowActionStepEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_action_step_events', function (Blueprint $table) {
            

    $table->id();

    $table->foreignId('workflow_action_step_id')
        ->constrained();

    /**
     * événement système
     */
    $table->string('event');

    /**
     * handler
     */
    $table->string('handler_class');

    /**
     * config dynamique
     */
    $table->json('config')
        ->nullable();

    /**
     * ordre exécution
     */
    $table->integer('execution_order')
        ->default(1);

    $table->boolean('is_active')
        ->default(true);

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
        Schema::dropIfExists('workflow_action_step_events');
    }
}
