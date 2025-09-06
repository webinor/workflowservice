<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowStatusHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_status_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('model'); // étape concernée
            $table->unsignedBigInteger('changed_by'); // user qui a modifié
            $table->enum('old_status', ['pending','in_progress','done','rejected'])->nullable();
            $table->enum('new_status', ['pending','in_progress','done','rejected']);
            $table->text('comment')->nullable(); // optionnel
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
        Schema::dropIfExists('workflow_status_histories');
    }
}
