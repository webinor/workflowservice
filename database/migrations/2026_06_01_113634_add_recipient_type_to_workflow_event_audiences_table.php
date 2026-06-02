<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_event_audiences', function (Blueprint $table) {

            $table->enum('recipient_type', [
                'TO',
                'CC',
                'BCC'
            ])
            ->default('TO')
            ->after('channel');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_event_audiences', function (Blueprint $table) {

            $table->dropColumn('recipient_type');

        });
    }
};