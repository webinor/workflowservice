<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->unsignedBigInteger('document_type_id')
                ->nullable()
                ->after('document_uuid');

            $table->string('document_type_slug')
                ->nullable()
                ->after('document_type_id');

            $table->string('document_type_version')
                ->nullable()
                ->after('document_type_slug');


            $table->index('document_type_id');
            $table->index('document_type_slug');
        });
    }


    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->dropIndex([
                'document_type_id'
            ]);

            $table->dropIndex([
                'document_type_slug'
            ]);

            $table->dropColumn([
                'document_type_id',
                'document_type_slug',
                'document_type_version'
            ]);
        });
    }
};