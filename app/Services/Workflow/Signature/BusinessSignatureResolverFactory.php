<?php

namespace App\Services\Workflow\Signature;

use App\Services\Workflow\Signature\BusinessSignatureResolver;
use App\Services\Workflow\Signature\TaxiBusinessSignatureResolver;
use Exception;

class BusinessSignatureResolverFactory
{
    public static function make(
        string $documentType
    ): BusinessSignatureResolver {

        switch ($documentType) {

            case 'papier-taxi':
                return new TaxiBusinessSignatureResolver();

               case 'note-de-frais':
                return new FeeNoteBusinessSignatureResolver();

                  case 'fiche-a-regulariser':
                return new RegularizationBusinessSignatureResolver();

                 case 'demande-d-absence':
                return new AbsenceBusinessSignatureResolver();

            default:
            // return new TaxiBusinessSignatureResolver();

            throw new Exception("Aucun business resolver defini pour : $documentType", 1);

            // case 'mission':
            //     // return new MissionBusinessSignatureResolver();

            // default:
            //     // return new EmptyBusinessSignatureResolver();
        }
    }
}