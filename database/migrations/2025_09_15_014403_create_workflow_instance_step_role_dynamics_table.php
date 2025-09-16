<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowInstanceStepRoleDynamicsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_instance_step_role_dynamics', function (Blueprint $table) {
            $table->id();

            // Étape spécifique de l'instance de workflow
            $table->unsignedBigInteger('workflow_instance_step_id');
            $table->foreign('workflow_instance_step_id', 'fk_wf_inst_step')
                ->references('id')
                ->on('workflow_instance_steps')
                ->onDelete('cascade');


            // Role de cet utilisateur pour cette étape
            $table->unsignedInteger('role_id');


            // Statut de l'utilisateur sur cette étape (optionnel)
            /*$table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED'])
                  ->default('PENDING');*/

            $table->timestamps();

            // On ne met pas de contrainte unique, car le même rôle peut apparaître plusieurs fois sur différentes instances
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_instance_step_users');
    }
}
