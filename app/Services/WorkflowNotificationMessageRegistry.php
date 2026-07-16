<?php

namespace App\Services;

use App\Services\Absence\AbsenceMessageBuilder;
use App\Services\FeeNote\FeeNoteMessageBuilder;
use App\Services\Mission\MissionEnricher;
use App\Services\Mission\MissionMessageBuilder;
use App\Services\Purchase\PurchaseEnricher;
use App\Services\Purchase\PurchaseRequestMessageBuilder;
use App\Services\Regularization\RegularizationMessageBuilder;
use App\Services\Taxi\TaxiPaperEnricher;
use App\Services\Taxi\TaxiPaperMessageBuilder;
use Exception;

class WorkflowNotificationMessageRegistry
{
    public function resolve(string $type)
    {
        if ($type === 'papier-taxi') {
            
            return new TaxiPaperMessageBuilder();
        }

        if ($type === 'mission') {
            return new MissionMessageBuilder();
        }

        if ($type === 'demande-achat') {
            return new PurchaseRequestMessageBuilder();
        }

        if ($type === 'note-de-frais') {
            return new FeeNoteMessageBuilder();
        }

        if ($type === 'demande-d-absence') {
            return new AbsenceMessageBuilder();
        }

        if ($type === 'fiche-a-regulariser') {
            return new RegularizationMessageBuilder();
        }



        

        throw new Exception(
            sprintf(
                'Aucun WorkflowNotificationMessageRegistry enregistré pour le type "%s".',
                $type
            )
        );

        // if ($type === 'MISSION') {
        //     return new MissionEnricher();
        // }

        // return new DefaultEnricher();
    }
}