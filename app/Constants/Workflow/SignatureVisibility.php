<?php

namespace App\Constants\Workflow;

class SignatureVisibility
{
    /**
     * Ne jamais afficher la signature.
     */
    const NEVER = 'NEVER';

    /**
     * Afficher uniquement si le participant a approuvé.
     */
    const IF_APPROVED = 'IF_APPROVED';

    /**
     * Toujours afficher la signature.
     */
    const ALWAYS = 'ALWAYS';
}