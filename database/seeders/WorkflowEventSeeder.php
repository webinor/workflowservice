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

        ];

        foreach ($events as $event) {

            WorkflowEvent::updateOrCreate(
                [
                    'code' => $event['code'],
                ],
                [
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'enabled' => true,
                ]
            );
        }
    }
}