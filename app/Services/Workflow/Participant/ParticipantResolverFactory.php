<?php

namespace App\Services\Workflow\Participant;

use App\Services\Workflow\Participant\Resolvers\MissionParticipantResolver;
use App\Services\Workflow\Participant\Resolvers\TaxiParticipantResolver;
use Exception;

class ParticipantResolverFactory
{
    public function make(string $type)
    {
         switch ($type) {

            case  'mission' :
             return   app(MissionParticipantResolver::class);


                 case
            'papier-taxi':
              return  app(TaxiParticipantResolver::class);


            default :
            throw new Exception("Aucun resolver defini", 1);
            
            //    return app(DefaultParticipantResolver::class);
        };
    }
}