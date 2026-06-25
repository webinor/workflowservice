<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->string('source_value')
            ->nullable()
            ->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->dropColumn('source_value');
        });
    }
};