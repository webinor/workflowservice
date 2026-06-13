<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instance_step_assignments', function (Blueprint $table) {
            $table->id();

            // Lien vers l'instance step
            $table->unsignedBigInteger('instance_step_id');

            // L'utilisateur qui peut ou a exécuté l'action
            $table->unsignedBigInteger('user_id')
            ->nullable();

            // Le rôle au moment de l'action
            $table->unsignedBigInteger('role_id');

            // STATIC | DYNAMIC
            $table->string('source_type')->nullable();

            // Actions métier
            $table->string('decision')->nullable(); 
            // PENDING | APPROVED | REJECTED | CANCELLED

            $table->timestamp('validated_at')->nullable();

            // Permissions spécifiques si besoin
            $table->boolean('can_validate')->default(true);
            $table->boolean('can_reject')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['instance_step_id']);
            $table->index(['user_id']);
            $table->index(['role_id']);

            // Foreign keys (adapte si tes tables ont d'autres noms)
            $table->foreign('instance_step_id')
                ->references('id')
                ->on('workflow_instance_steps')
                ->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_step_assignments');
    }
};