<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->timestamp('due_date')->nullable()->after('status')->comment('Date limite pour validation de l\'étape');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
