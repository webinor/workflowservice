<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToWorkflowConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {


            // Transition associée
            $table->foreignId('workflow_transition_id')
                  ->nullable()
                  ->after('workflow_step_id')
                  ->constrained('workflow_transitions')
                  ->nullOnDelete();

            // Kind de condition (BLOCKING / PATH)
            $table->enum('condition_kind', ['BLOCKING', 'PATH'])
                  ->after('workflow_transition_id');
                  //->default('BLOCKING');

            // Type d’élément requis (ex: AttachmentType)
            $table->string('required_type')->nullable()->after('condition_kind');

            // ID de l’élément requis (ex: id du document)
            $table->unsignedMediumInteger('required_id')->nullable()->after('required_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->dropForeign(['workflow_transition_id']);
            $table->dropColumn([
                'workflow_transition_id',
                'condition_kind',
                'required_type',
                'required_id'
            ]);
        });
    }
}
