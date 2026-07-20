<?php

namespace App\Services;

use App\Services\FeeNote\FeeNoteEnricher;
use App\Services\Mission\MissionEnricher;
use App\Services\Purchase\PurchaseEnricher;
use App\Services\Taxi\TaxiPaperEnricher;
use App\Services\Absence\AbsenceEnricher;
use App\Services\Regularization\RegularizationEnricher;
use Exception;

class DocumentEnricherRegistry
{
    public function resolve(string $type)
    {
        if ($type === 'papier-taxi') {
            
            return new TaxiPaperEnricher();
        }

        if ($type === 'mission') {

            return new MissionEnricher();
            
        }

        if ($type === "demande-achat") {
            return new PurchaseEnricher();
        }


        if ($type === "note-de-frais") {
            return new FeeNoteEnricher();
        }

        if ($type === "demande-d-absence") {
            return new AbsenceEnricher();
        }

        if ($type === "fiche-a-regulariser") {
            return new RegularizationEnricher();
        }

        if ($type === "facture-fournisseur") {
            return new RegularizationEnricher();
        }

        throw new Exception(
            sprintf(
                'Aucun DocumentEnricher enregistré pour le type "%s".',
                $type
            )
        );

        // if ($type === 'MISSION') {
        //     return new MissionEnricher();
        // }

        // return new DefaultEnricher();
    }
}