<?php

namespace App\Services\Workflow\Amount;

interface AmountSourceInterface
{
    /**
     * Calcule le montant à partir des données du document.
     *
     * Chaque stratégie reçoit :
     * - Les données complètes du document.
     * - Sa propre configuration.
     *
     * @param array $documentData
     * @param array $config
     * @return float
     */
    public function resolve(
        array $documentData,
        array $config
    ): float;
}