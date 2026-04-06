<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('workflow_transition_id');

            // index pour performance
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};