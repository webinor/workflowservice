<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowActionType;

class WorkflowActionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            [
                'code' => 'VALIDATION',
                'label' => 'Validation',
                'description' => 'Action permettant de valider un document.',
            ],

            [
                'code' => 'UPLOAD',
                'label' => 'Upload',
                'description' => 'Action permettant d’uploader un fichier.',
            ],

            [
                'code' => 'REJECTION',
                'label' => 'Rejet',
                'description' => 'Action permettant de rejeter un document.',
            ],

            [
                'code' => 'RETURN',
                'label' => 'Retour pour modification',
                'description' => 'Action permettant de retourner un document pour modification.',
            ],

            [
                'code' => 'CANCEL',
                'label' => 'Annulation',
                'description' => 'Action permettant d’annuler un workflow ou un document.',
            ],

            [
                'code' => 'BYPASS',
                'label' => 'Bypass',
                'description' => 'Action permettant de contourner l’étape actuelle du workflow.',
            ],
        ];

        foreach ($actions as $action) {
            WorkflowActionType::updateOrCreate(
                ['code' => $action['code']],
                $action
            );
        }
    }
}