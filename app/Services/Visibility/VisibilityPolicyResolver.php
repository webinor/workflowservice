<?php

namespace App\Services\Visibility;

use App\Enums\DocumentTypeCode;
use App\Services\Visibility\Policies\AbsenceVisibilityPolicy;
use App\Services\Visibility\Policies\FeeNoteVisibilityPolicy;
use App\Services\Visibility\Policies\SupplierInvoiceVisibilityPolicy;
use App\Services\Visibility\Policies\TaxiPaperVisibilityPolicy;
use Exception;

class VisibilityPolicyResolver
{
    public function resolve(string $documentType): VisibilityPolicyInterface
    {
        switch ($documentType) {

            case DocumentTypeCode::PAPIER_TAXI:
                return app(
                    TaxiPaperVisibilityPolicy::class
                );

                  case DocumentTypeCode::NOTE_DE_FRAIS:
                return app(
                    FeeNoteVisibilityPolicy::class
                );


            case DocumentTypeCode::DEMANDE_ABSENCE:
                return app(
                    AbsenceVisibilityPolicy::class
                );

            case DocumentTypeCode::FACTURE_FOURNISSEUR:
                return app(
                    SupplierInvoiceVisibilityPolicy::class
                );

            default:
                throw new Exception(
                    "Aucune policy pour {$documentType}"
                );
        }
    }
}