<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('bypassed_by')
                ->nullable()
                ->after('executed_at');

            $table->timestamp('bypassed_at')
                ->nullable()
                ->after('bypassed_by');

            $table->text('bypass_reason')
                ->nullable()
                ->after('bypassed_at');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->dropColumn([
                'bypassed_by',
                'bypassed_at',
                'bypass_reason',
            ]);
        });
    }
};