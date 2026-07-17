<?php

namespace App\Services\Workflow\Participant;

use App\Services\Workflow\Participant\Resolvers\AbsenceParticipantResolver;
use App\Services\Workflow\Participant\Resolvers\FeeNoteParticipantResolver;
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

                      case
            'demande-d-absence':
              return  app(AbsenceParticipantResolver::class);

                         case
            'note-de-frais':
              return  app(FeeNoteParticipantResolver::class);

                          case
            'fiche-a-regulariser':
              return  app(FeeNoteParticipantResolver::class);


            default :
            throw new Exception("Aucun resolver defini pour : $type", 1);
            
            //    return app(DefaultParticipantResolver::class);
        };
    }
}