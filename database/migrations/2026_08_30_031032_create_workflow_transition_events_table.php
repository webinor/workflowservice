<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transition_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('transition_id');
            $table->unsignedBigInteger('event_id');

            $table->timestamps();

            $table->unique(
                ['transition_id', 'event_id'],
                'workflow_transition_events_transition_event_unique'
            );

            $table->foreign('transition_id', 'wte_transition_fk')
                ->references('id')
                ->on('workflow_transitions')
                ->cascadeOnDelete();

            $table->foreign('event_id', 'wte_event_fk')
                ->references('id')
                ->on('workflow_events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transition_events');
    }
};