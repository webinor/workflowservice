<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // Supprimer l'ancien champ string
            if (Schema::hasColumn('workflow_steps', 'status_label')) {
                $table->dropColumn('status_label');
            }

            // Ajouter la clé étrangère
            $table->unsignedBigInteger('workflow_status_label_id')->nullable()->after('name');

            $table->foreign('workflow_status_label_id')
                  ->references('id')
                  ->on('workflow_status_labels')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['workflow_status_label_id']);
            $table->dropColumn('workflow_status_label_id');

            $table->string('status_label')->after('name');
        });
    }
};