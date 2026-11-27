<?php

namespace App\Services\Workflow\Amount;

use InvalidArgumentException;

class SumFieldsAmountSource implements AmountSourceInterface
{
    /**
     * Additionne plusieurs champs du document.
     *
     * Exemple :
     * material_amount + transport_amount + additional_cost
     *
     * Chaque champ est défini par son chemin dans documentData.
     * 
     * {
    "purchase_request": {
        "material_amount": 300000,
        "transport_amount": 50000,
        "additional_cost": 25000
    }
}

{
    "type": "SUM_FIELDS",
    "fields": [
        "purchase_request.material_amount",
        "purchase_request.transport_amount",
        "purchase_request.additional_cost"
    ]
}
    
     */
    public function resolve(
        array $documentData,
        array $config
    ): float {

        $fields = $config['fields'] ?? [];

        if (empty($fields) || !is_array($fields)) {
            throw new InvalidArgumentException(
                'La configuration SUM_FIELDS nécessite une liste de champs.'
            );
        }

        $total = 0;

        foreach ($fields as $field) {

            $value = data_get($documentData, $field);

            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Le champ {$field} ne contient pas un montant numérique."
                );
            }

            $total += (float) $value;
        }

        return $total;
    }
}