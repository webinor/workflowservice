<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignatureAndParaphFieldsToWorkflowStepsAndAssignmentsTables extends Migration
{
    /**
     * Ajouter les règles d'affichage des signatures et des paraphes.
     *
     * workflow_steps
     * --------------
     * Contient la configuration par défaut définie lors de la conception du workflow.
     *
     * workflow_instance_step_assignments
     * ----------------------------------
     * Contient une copie de cette configuration au moment de la création
     * de l'instance du workflow. Cela garantit que les anciennes instances
     * ne sont pas impactées si le workflow est modifié ultérieurement.
     *
     * Enum métier
     * ===========
     *
     * signature_visibility
     * --------------------
     * NEVER        : Ne jamais afficher la signature.
     * IF_APPROVED  : Afficher uniquement si le participant a approuvé l'étape.
     * ALWAYS       : Toujours afficher la signature.
     *
     * signature_mode
     * --------------
     * NONE                : Ne rien afficher.
     * NAME_ONLY           : Afficher uniquement le nom du participant.
     * SIGNATURE_ONLY      : Afficher uniquement l'image de la signature.
     * NAME_AND_SIGNATURE  : Afficher le nom et la signature.
     *
     * paraph_visibility
     * -----------------
     * NEVER        : Ne jamais afficher le paraphe.
     * IF_APPROVED  : Afficher uniquement après approbation.
     * ALWAYS       : Toujours afficher le paraphe.
     *
     * paraph_mode
     * -----------
     * NONE                 : Ne rien afficher.
     * INITIALS_ONLY        : Afficher uniquement les initiales.
     * PARAPH_ONLY          : Afficher uniquement l'image du paraphe.
     * INITIALS_AND_PARAPH  : Afficher les initiales et le paraphe.
     *
     * Remarque
     * --------
     * Les valeurs sont volontairement stockées sous forme de chaînes
     * afin de rester compatibles avec PHP 7.4 et d'être facilement
     * exploitables par le frontend React.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {

            /*
             * Configuration de la signature
             */
            $table->string('signature_visibility')
                ->default('IF_APPROVED')
                ->after('position');

            $table->string('signature_mode')
                ->default('NAME_AND_SIGNATURE')
                ->after('signature_visibility');

            /*
             * Configuration du paraphe
             */
            $table->string('paraph_visibility')
                ->default('NEVER')
                ->after('signature_mode');

            $table->string('paraph_mode')
                ->default('INITIALS_AND_PARAPH')
                ->after('paraph_visibility');
        });

        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {

            /*
             * Copie de la configuration de signature
             * provenant du WorkflowStep.
             */
            $table->string('signature_visibility')
                ->default('IF_APPROVED')
                ->after('decision');

            $table->string('signature_mode')
                ->default('NAME_AND_SIGNATURE')
                ->after('signature_visibility');

            /*
             * Copie de la configuration de paraphe
             * provenant du WorkflowStep.
             */
            $table->string('paraph_visibility')
                ->default('NEVER')
                ->after('signature_mode');

            $table->string('paraph_mode')
                ->default('INITIALS_AND_PARAPH')
                ->after('paraph_visibility');
        });
    }

    /**
     * Supprimer les règles d'affichage des signatures et des paraphes.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_steps', function (Blueprint $table) {

            $table->dropColumn([
                'signature_visibility',
                'signature_mode',
                'paraph_visibility',
                'paraph_mode',
            ]);
        });

        Schema::table('workflow_instance_step_assignments', function (Blueprint $table) {

            $table->dropColumn([
                'signature_visibility',
                'signature_mode',
                'paraph_visibility',
                'paraph_mode',
            ]);
        });
    }
}