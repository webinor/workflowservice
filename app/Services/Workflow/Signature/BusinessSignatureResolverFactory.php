<?php

namespace App\Services\Workflow\Signature;

use App\Services\Workflow\Signature\BusinessSignatureResolver;
use App\Services\Workflow\Signature\TaxiBusinessSignatureResolver;


class BusinessSignatureResolverFactory
{
    public static function make(
        string $documentType
    ): BusinessSignatureResolver {

        switch ($documentType) {

            case 'papier-taxi':
                return new TaxiBusinessSignatureResolver();

            default:
            return new TaxiBusinessSignatureResolver();
            // case 'mission':
            //     // return new MissionBusinessSignatureResolver();

            // default:
            //     // return new EmptyBusinessSignatureResolver();
        }
    }
}