<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeDocumentTypeFieldsNotNullableOnWorkflowInstancesTable extends Migration
{
    public function up()
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->unsignedBigInteger('document_type_id')
                ->nullable(false)
                ->change();

            $table->string('document_type_relation_name')
                ->nullable(false)
                ->change();

            $table->string('document_type_version')
                ->default(1)
                ->nullable(false)
                ->change();
        });
    }

    public function down()
    {
        Schema::table('workflow_instances', function (Blueprint $table) {

            $table->unsignedBigInteger('document_type_id')
                ->nullable()
                ->change();

            $table->string('document_type_relation_name')
                ->nullable()
                ->change();

            $table->string('document_type_version')
                ->nullable()
                ->change();
        });
    }
}