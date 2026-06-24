<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowHandlersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_handlers', function (Blueprint $table) {
             $table->id(); 
           $table->foreignId('workflow_event_id') 
           ->constrained('workflow_events') 
           ->cascadeOnDelete(); 
           $table->string('handler_class'); 
           $table->integer('priority')->default(0); 
           $table->boolean('is_async')->default(false); 
           $table->boolean('enabled')->default(true); 
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
        Schema::dropIfExists('workflow_handlers');
    }
}
