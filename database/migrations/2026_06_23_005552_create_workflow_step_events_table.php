<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowStepEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_step_events', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('workflow_step_id') ->constrained('workflow_steps') ->cascadeOnDelete(); 
            $table->foreignId('workflow_event_id') ->constrained('workflow_events') ->cascadeOnDelete(); 
            $table->integer('priority')->default(0); 
            $table->boolean('enabled')->default(true); 
            $table->timestamps();
            $table->unique([ 'workflow_step_id', 'workflow_event_id' ], 'workflow_step_event_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_step_events');
    }
}
