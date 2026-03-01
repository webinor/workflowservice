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
                'label' => "Upload",
                'description' => 'Action permettant d’uploader un fichier.',
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