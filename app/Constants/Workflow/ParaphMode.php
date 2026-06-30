<?php

namespace App\Constants\Workflow;


class ParaphMode
{
    /**
     * Ne rien afficher.
     */
    const NONE = 'NONE';

    /**
     * Afficher uniquement les initiales.
     */
    const INITIALS_ONLY = 'INITIALS_ONLY';

    /**
     * Afficher uniquement le paraphe.
     */
    const PARAPH_ONLY = 'PARAPH_ONLY';

    /**
     * Afficher les initiales et le paraphe.
     */
    const INITIALS_AND_PARAPH = 'INITIALS_AND_PARAPH';

    /**
     * Retourne toutes les valeurs autorisées.
     *
     * @return array
     */
    public static function values()
    {
        return [
            self::NONE,
            self::INITIALS_ONLY,
            self::PARAPH_ONLY,
            self::INITIALS_AND_PARAPH,
        ];
    }
}