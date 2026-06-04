<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('signature_type_id')
                ->constrained()
                ->cascadeOnDelete();

            // Référence du document GED
            $table->unsignedBigInteger('document_id');

            // Utilisateur ayant signé
            $table->unsignedBigInteger('user_id');

            // Commentaire éventuel
            $table->text('comment')->nullable();

            // Signature réalisée le
            $table->timestamp('signed_at');

            $table->timestamps();

            $table->index('document_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};