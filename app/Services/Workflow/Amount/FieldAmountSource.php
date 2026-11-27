<?php

namespace App\Services\Workflow\Amount;

use InvalidArgumentException;

class FieldAmountSource implements AmountSourceInterface
{
    /**
     * Récupère un montant depuis un champ du document.
     *
     * Exemple :
     * field = purchase_request.amount
     *
     * data_get() permet de parcourir les structures imbriquées.
     */
    public function resolve(
        array $documentData,
        array $config
    ): float {

        $field = $config['field'] ?? null;

        if (!$field) {
            throw new InvalidArgumentException(
                'La configuration FIELD nécessite un champ.'
            );
        }

        $value = data_get($documentData, $field);

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "Le champ {$field} ne contient pas un montant numérique."
            );
        }

        return (float) $value;
    }
}