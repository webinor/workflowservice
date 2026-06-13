<?php

namespace App\Services;

use App\Services\Taxi\TaxiPaperEnricher;
use Exception;

class DocumentEnricherRegistry
{
    public function resolve(string $type)
    {
        if ($type === 'papier-taxi') {
           
            
            return new TaxiPaperEnricher();
        }

        // if ($type === 'MISSION') {
        //     return new MissionEnricher();
        // }

        // return new DefaultEnricher();
    }
}