<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {

            $table->unsignedBigInteger('employee_id')
                ->nullable()
                ->after('document_id');

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
