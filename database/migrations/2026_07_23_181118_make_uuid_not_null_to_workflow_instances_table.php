<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeUuidNotNullToWorkflowInstancesTable extends Migration
{
     public function up()
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->uuid('document_uuid')
                ->after('document_id')
                  ->nullable(false)
        ->unique()
        ->change();
        });
    }

    public function down()
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->uuid('document_uuid')
             ->nullable()
             ->change();
        });
    }
}
