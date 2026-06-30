<?php

namespace App\Constants\Workflow;


class ParaphVisibility
{
    /**
     * Ne jamais afficher le paraphe.
     */
    const NEVER = 'NEVER';

    /**
     * Afficher uniquement si le participant a approuvé.
     */
    const IF_APPROVED = 'IF_APPROVED';

    /**
     * Toujours afficher le paraphe.
     */
    const ALWAYS = 'ALWAYS';

    /**
     * Retourne toutes les valeurs autorisées.
     *
     * @return array
     */
    public static function values()
    {
        return [
            self::NEVER,
            self::IF_APPROVED,
            self::ALWAYS,
        ];
    }
}