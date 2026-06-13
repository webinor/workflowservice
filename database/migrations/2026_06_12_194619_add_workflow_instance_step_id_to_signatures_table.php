<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {

            // lien vers l'étape du workflow
            $table->unsignedBigInteger('workflow_instance_step_id')
                ->nullable()
                ->after('document_id');

            // index pour perf
            $table->index('workflow_instance_step_id');

            // clé étrangère (optionnel mais recommandé)
            $table->foreign('workflow_instance_step_id')
                ->references('id')
                ->on('workflow_instance_steps')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {

            $table->dropForeign(['workflow_instance_step_id']);
            $table->dropIndex(['workflow_instance_step_id']);

            $table->dropColumn('workflow_instance_step_id');
        });
    }
};