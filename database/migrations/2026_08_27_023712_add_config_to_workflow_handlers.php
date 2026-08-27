<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfigToWorkflowHandlers extends Migration
{
    public function up()
    {
        Schema::table('workflow_handlers', function (Blueprint $table) {
            $table->json('config')
                ->nullable()
                ->after('is_async');
        });
    }

    public function down()
    {
        Schema::table('workflow_handlers', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
}