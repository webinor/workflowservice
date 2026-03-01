<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {

            $table->foreignId('workflow_action_type_id')
                  ->after('id')/*
                  ->constrained('workflow_action_types')
                  ->cascadeOnDelete()*/;

            $table->integer('position')
            ->after('workflow_action_type_id')
            ->default(0);

        });
    }

    public function down(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {

            $table->dropForeign(['workflow_action_type_id']);
            $table->dropColumn(['workflow_action_type_id'.'position']);

        });
    }
};