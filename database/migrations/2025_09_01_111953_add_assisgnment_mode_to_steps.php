<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssisgnmentModeToSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
        
            $table->enum('assignment_mode',['STATIC', 'DYNAMIC'])->default('STATIC')->after('workflow_id');

              # Si STATIC
    #role_id BIGINT NULL,        -- Ex: "Responsable Comptabilité"
    #user_id BIGINT NULL,        -- Cas rare : assigné à un utilisateur précis
            $table->unsignedSmallInteger('role_id')->nullable()->after('assignment_mode');
            $table->unsignedSmallInteger('user_id')->nullable()->after('role_id');

                # Si DYNAMIC
    #-- Exemple: "department.responsible_user"
    #-- ou "document.creator", "document.requester_department.responsible"
            $table->string('dynamic_rule')->nullable()->after('user_id');


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn(['assignment_mode','role_id','user_id','dynamic_rule']);
        });
    }
}
