<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowInstanceStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_instance_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->onDelete('cascade');
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->onDelete('cascade');
            $table->foreignId('user_id')->nullable(); // utilisateur ayant agi
            $table->string('status')->default('pending'); // pending, approved, rejected, in_progress
            $table->text('comments')->nullable();
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
        Schema::dropIfExists('workflow_instance_steps');
    }
}
