<?php

namespace App\Enums;

class ResponsibilityCode
{
    /*
    |--------------------------------------------------------------------------
    | Visibilité
    |--------------------------------------------------------------------------
    */

    const VIEW_ALL_DOCUMENTS =
        'VIEW_ALL_DOCUMENTS';

    const VIEW_ALL_TAXI_PAPERS =
        'VIEW_ALL_TAXI_PAPERS';

    const VIEW_ALL_ABSENCES =
        'VIEW_ALL_ABSENCES';

    const VIEW_ALL_FINANCIAL_DOCUMENTS =
        'VIEW_ALL_FINANCIAL_DOCUMENTS';


    /*
    |--------------------------------------------------------------------------
    | Papier Taxi
    |--------------------------------------------------------------------------
    */

    const VALIDATE_TAXI_PAPER =
        'VALIDATE_TAXI_PAPER';


    /*
    |--------------------------------------------------------------------------
    | Absence
    |--------------------------------------------------------------------------
    */

    const APPROVE_ABSENCE =
        'APPROVE_ABSENCE';


    /*
    |--------------------------------------------------------------------------
    | Facture fournisseur
    |--------------------------------------------------------------------------
    */

    const APPROVE_INVOICE =
        'APPROVE_INVOICE';

    const PREPARE_PAYMENT =
        'PREPARE_PAYMENT';
}