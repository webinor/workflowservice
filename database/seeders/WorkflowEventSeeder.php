<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowEvent;

class WorkflowEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $events = [

            [
                'code' => 'GENERATE_MISSION_DOCUMENTS',
                'name' => 'Génération des documents de mission',
                'description' => 'Génère automatiquement les documents liés à une mission.',
            ],

            [
                'code' => 'GENERATE_LEAVE_DOCUMENTS',
                'name' => 'Génération des documents de congé',
                'description' => 'Génère automatiquement les documents liés à une demande de congé (PDF, arrêté de congé, pièces jointes, etc.).',
            ],

            [
                'code' => 'DEDUCT_LEAVE_DAYS',
                'name' => 'Déduction des jours de congé',
                'description' => 'Calcule les jours imputables et met à jour le solde de congé du collaborateur.',
            ],

            [
                'code' => 'NOTIFY_TAXI_PAPER_BENEFICIARY',
                'name' => 'Notification du bénéficiaire du papier taxi',
                'description' => 'Envoie une notification au bénéficiaire pour l’informer que sa demande de papier taxi a été validée.',
            ],

            [
                'code' => 'DOCUMENT_RETURNED',
                'name' => 'Document retourné',
                'description' => 'Déclenché lorsqu’un document est retourné à l’étape précédente du workflow pour correction ou complément d’information.',
            ],

            [
                'code' => 'DOCUMENT_REJECTED',
                'name' => 'Document rejeté',
                'description' => 'Déclenché lorsqu’un document est définitivement rejeté au cours du workflow.',
            ],

            [
                'code' => 'DOCUMENT_VALIDATED',
                'name' => 'Document validé',
                'description' => 'Déclenché lorsqu’un document est validé au cours du workflow.',
            ],

            [
                'code' => 'DOCUMENT_WORKFLOW_COMPLETED',
                'name' => 'Workflow du document terminé',
                'description' => 'Déclenché lorsque toutes les étapes du workflow du document sont terminées.',
            ],

            [
    'code' => 'REGULARIZATION_SHEET_REGULARIZED',
    'name' => 'Fiche de régularisation régularisée',
    'description' => 'Déclenché lorsqu’une fiche de régularisation a été entièrement régularisée et que toutes les étapes requises sont terminées.',
],

[
    'code' => 'REGULARIZATION_SHEET_READY_FOR_FINALIZATION',
    'name' => 'Fiche de régularisation prête à être finalisée',
    'description' => 'Déclenché lorsque toutes les pièces justificatives requises de la fiche de régularisation ont été signées et que la fiche est prête pour sa finalisation.',
],

[
    'code' => 'LEAVE_REQUEST_APPROVED',
    'name' => 'Demande de congé approuvée',
    'description' => 'Déclenché lorsqu’une demande de congé est approuvée par le workflow.',
],




            /*
    |--------------------------------------------------------------------------
    | Signatures
    |--------------------------------------------------------------------------
    */

    [
        'code' => 'REGULARIZATION_SHEET_SIGNED',
        'name' => 'Fiche de régularisation signée',
        'description' => 'Déclenché lorsqu’une fiche de régularisation est signée.',
    ],

    [
        'code' => 'TAXI_PAPER_SIGNED',
        'name' => 'Papier taxi signé',
        'description' => 'Déclenché lorsqu’un papier taxi est signé.',
    ],

    [
        'code' => 'FEE_NOTE_SIGNED',
        'name' => 'Note de frais signée',
        'description' => 'Déclenché lorsqu’une note de frais est signée.',
    ],

    [
    'code' => 'APPLY_REGULARIZATION_SUPPORTING_DOCUMENT_SIGNATURES',
    'name' => 'Apposition des signatures sur les pièces justificatives',
    'description' => 'Appose automatiquement sur les pièces justificatives d’une fiche de régularisation les signatures configurées aux positions prévues.',
],

        ];

        foreach ($events as $event) {

            WorkflowEvent::updateOrCreate(
                [
                    'code' => $event['code'],
                ],
                [
                    'name' => $event['name'],
                    'description' => $event['description'],
                    // 'enabled' => true,
                ]
            );
        }
    }
}