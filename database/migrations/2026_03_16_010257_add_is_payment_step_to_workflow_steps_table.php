<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->boolean('is_payment_step')
                  ->default(false)
                  ->after('workflow_status_label_id')
                  ->comment('Indique si cette étape déclenche un paiement');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('is_payment_step');
        });
    }
};