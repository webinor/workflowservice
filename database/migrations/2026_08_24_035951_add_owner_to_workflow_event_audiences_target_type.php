<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE workflow_event_audiences
            MODIFY COLUMN target_type ENUM(
                'ROLE',
                'USER',
                'ACTOR',
                'OWNER',
                'STEP_VALIDATOR',
                'SERVICE_HEAD',
                'DYNAMIC'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE workflow_event_audiences
            MODIFY COLUMN target_type ENUM(
                'ROLE',
                'USER',
                'ACTOR',
                'STEP_VALIDATOR',
                'SERVICE_HEAD',
                'DYNAMIC'
            ) NOT NULL
        ");
    }
};