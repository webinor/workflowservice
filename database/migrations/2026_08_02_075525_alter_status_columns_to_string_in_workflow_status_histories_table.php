<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AlterStatusColumnsToStringInWorkflowStatusHistoriesTable extends Migration
{
    public function up()
    {
        DB::statement("
            ALTER TABLE workflow_status_histories
            MODIFY old_status VARCHAR(40) NULL
        ");

        DB::statement("
            ALTER TABLE workflow_status_histories
            MODIFY new_status VARCHAR(40) NOT NULL
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE workflow_status_histories
            MODIFY old_status ENUM(
                'PENDING',
                'IN_PROGRESS',
                'NOT_STARTED',
                'COMPLETE',
                'COMPLETED',
                'REJECTED'
            ) NULL
        ");

        DB::statement("
            ALTER TABLE workflow_status_histories
            MODIFY new_status ENUM(
                'PENDING',
                'IN_PROGRESS',
                'NOT_STARTED',
                'COMPLETE',
                'COMPLETED',
                'REJECTED'
            ) NOT NULL
        ");
    }
}