<?php

namespace App\Enums;

class NotificationPolicy
{
    /**
     * Les identifiants fournis sont des role_id.
     * Le User Service doit résoudre les utilisateurs.
     */
    public const ROLE = 'ROLE';

    /**
     * Les identifiants fournis sont directement des user_id.
     * Aucune résolution par rôle n'est nécessaire.
     */
    public const USER = 'USER';
}