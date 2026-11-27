<?php

namespace App\Services\Workflow\Amount;

use Exception;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SumAmountSource implements AmountSourceInterface
{
    /**
     * Additionne un champ présent dans chaque élément
     * d'une collection.
     *
     * Exemple :
     * 150000 + 200000 = 350000
     *
     * La collection peut être un tableau ou une Collection Laravel.
     * 
     * {
     *"purchase_request": {
        "purchase_request_items": [
            {
                "quantity": 2,
                "total_amount": 150000
            },
            {
                "quantity": 3,
                "total_amount": 200000
            }
        ]
    }
}

{
    "type": "SUM",
    "collection": "purchase_request.purchase_request_items",
    "field": "total_amount"
}

     * 
     */
    public function resolve(
        array $documentData,
        array $config
    ): float {



        $collectionPath = $config['collection'] ?? null;
        $field = $config['field'] ?? null;

        if (!$collectionPath || !$field) {
            throw new InvalidArgumentException(
                'La configuration SUM nécessite collection et field.'
            );
        }

        $items = data_get($documentData, $collectionPath);



        if ($items instanceof Collection) {
            $items = $items->all();
        }

        if (!is_array($items)) {
            throw new InvalidArgumentException(
                "La source {$collectionPath} n'est pas une collection valide."
            );
        }

        $total = 0;

        foreach ($items as $index => $item) {

            $value = data_get($item, $field);

            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Le montant {$field} de l'élément {$index} est invalide."
                );
            }

            $total += (float) $value;
        }

        // throw new Exception(json_encode($total), 1);


        return $total;
    }
}