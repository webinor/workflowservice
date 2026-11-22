<?php

namespace App\Services\Client;

class ClientServiceClient
{
    /**
     * Récupération temporaire des informations du client.
     *
     * À remplacer ultérieurement par un appel au Client Service.
     */
    public function getClient(int $clientId): ?array
    {
        return [
            'id' => $clientId,
            'name' => 'Client #' . $clientId,
            'nom' => 'Client #' . $clientId,
        ];
    }
}