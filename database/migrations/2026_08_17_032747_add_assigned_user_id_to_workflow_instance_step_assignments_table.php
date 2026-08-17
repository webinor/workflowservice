<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('assigned_user_id')
                ->nullable()
                ->after('role_id');

            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->dropIndex([
                'workflow_instance_step_assignments_assigned_user_id_index'
            ]);

            $table->dropColumn('assigned_user_id');
        });
    }
};