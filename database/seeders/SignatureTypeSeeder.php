<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SignatureType;

class SignatureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $signatureTypes = [
            [
                'code' => 'TAXI_PAPER_SETTLEMENT',
                'name' => 'Réception des frais de taxi',
                'description' => 'Signature attestant la réception des frais de taxi',
            ],
            [
                'code' => 'ADVANCE_RECEIPT',
                'name' => 'Réception de l\'avance',
                'description' => 'Signature attestant la réception de l\'avance de mission',
            ],
            [
                'code' => 'MISSION_REPORT',
                'name' => 'Validation du rapport de mission',
                'description' => 'Signature du rapport de mission',
            ],
            [
                'code' => 'EXPENSE_REPORT',
                'name' => 'Validation de l\'état de frais',
                'description' => 'Signature de l\'état de frais',
            ],
            [
                'code' => 'EQUIPMENT_RECEIPT',
                'name' => 'Réception du matériel',
                'description' => 'Signature attestant la réception du matériel',
            ],
            [
                'code' => 'DOCUMENT_APPROVAL',
                'name' => 'Validation de document',
                'description' => 'Validation générique d\'un document',
            ],
        ];

        foreach ($signatureTypes as $type) {
            SignatureType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}