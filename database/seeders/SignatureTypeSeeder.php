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
            [
                'code' => 'FEE_NOTE_SETTLEMENT',
                'name' => 'Réception du paiement de la note de frais',
                'description' => 'Signature attestant la réception du paiement d\'une note de frais',
            ],

            // ===== FICHE À RÉGULARISER =====

            [
                'code' => 'REGULARIZATION_ADVANCE',
                'name' => 'Paiement de la fiche à régulariser',
                'description' => 'Signature attestant la réception de l\'avance ou du paiement lié à une fiche à régulariser',
            ],
            [
                'code' => 'REGULARIZATION_SETTLEMENT',
                'name' => 'Régularisation de la fiche',
                'description' => 'Signature attestant la régularisation définitive d\'une fiche à régulariser',
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