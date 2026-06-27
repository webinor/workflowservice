<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSignaturesReplaceUserByActor extends Migration
{
    public function up()
    {
        Schema::table('signatures', function (Blueprint $table) {

            // Suppression de l'index sur user_id
            $table->dropIndex(['user_id']);

            // Suppression de la colonne user_id
            $table->dropColumn('user_id');

            //             // Suppression de l'index sur employee_id
            // $table->dropIndex(['employee_id']);

            // // Suppression de la colonne employee_id
            // $table->dropColumn('employee_id');

            // Ajout des informations de l'acteur
            $table->string('actor_type', 50)->after('document_id');

            $table->unsignedBigInteger('actor_id')
                ->after('actor_type');

            $table->string('actor_name')
                ->nullable()
                ->after('actor_id');

            $table->string('actor_role')
                ->nullable()
                ->after('actor_name');

            // Index pour les recherches
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down()
    {
        Schema::table('signatures', function (Blueprint $table) {

            $table->dropIndex(['actor_type', 'actor_id']);

            $table->dropColumn([
                'actor_type',
                'actor_id',
                'actor_name',
                'actor_role',
            ]);

            $table->unsignedBigInteger('user_id')
                ->after('document_id');

            $table->index('user_id');

            //             $table->unsignedBigInteger('employee_id')
            //             ->nullable()
            //     ->after('document_id');

            // $table->index('employee_id');
        });
    }
}