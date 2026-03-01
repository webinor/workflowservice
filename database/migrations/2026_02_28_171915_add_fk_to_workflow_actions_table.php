<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {

            $table->foreign('workflow_action_type_id')
                  ->references('id')
                  ->on('workflow_action_types')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_actions', function (Blueprint $table) {

            $table->dropForeign(['workflow_action_type_id']);
        });
    }
};