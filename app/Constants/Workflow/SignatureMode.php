<?php

namespace App\Constants\Workflow;

class SignatureMode
{
    /**
     * N'afficher aucun élément.
     */
    const NONE = 'NONE';

    /**
     * Afficher uniquement le nom.
     */
    const NAME_ONLY = 'NAME_ONLY';

    /**
     * Afficher uniquement la signature.
     */
    const SIGNATURE_ONLY = 'SIGNATURE_ONLY';

    /**
     * Afficher le nom et la signature.
     */
    const NAME_AND_SIGNATURE = 'NAME_AND_SIGNATURE';
}