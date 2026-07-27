<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeUuidNotNullToSignaturesTable extends Migration
{
     public function up()
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->uuid('document_uuid')
                ->after('document_id')
                  ->nullable(false)
        ->change();
        });
    }

    public function down()
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->uuid('document_uuid')
             ->nullable()
             ->change();
        });
    }
}
