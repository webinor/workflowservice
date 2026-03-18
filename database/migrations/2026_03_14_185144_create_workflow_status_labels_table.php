<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('workflow_status_labels', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('label');        // Exemple : "Validation en cours"
            $table->string('emoji')->nullable(); // Exemple : "🟡"
            $table->string('color')->nullable(); // Exemple : "warning", "success"
            $table->boolean('is_configurable')
                  ->default(true)
                  ->comment('Indique si le label peut être affiché dans la configuration du workflow');
     
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workflow_status_labels');
    }
};