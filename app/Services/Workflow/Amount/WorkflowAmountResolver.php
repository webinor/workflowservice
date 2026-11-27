<?php

namespace App\Services\Workflow\Amount;

use Exception;
use InvalidArgumentException;

class WorkflowAmountResolver
{
    /**
     * Résout la source de calcul du montant.
     *
     * Types pris en charge :
     *
     * FIELD      : lecture d'un champ unique.
     * SUM        : somme d'un champ dans une collection.
     * SUM_FIELDS : somme de plusieurs champs.
     *
     * Pour ajouter une nouvelle stratégie :
     * 1. Créer une classe implémentant AmountSourceInterface.
     * 2. Ajouter son type dans ce switch.
     */
    public function resolve(
        array $documentData,
        array $config
    ): float {

        $type = strtoupper($config['type'] ?? '');



        switch ($type) {

            case 'FIELD':

                $source = new FieldAmountSource();

                break;

            case 'SUM':

                $source = new SumAmountSource();

                break;

            case 'SUM_FIELDS':

                $source = new SumFieldsAmountSource();

                break;

            default:

                throw new InvalidArgumentException(
                    "Type de source de montant non supporté : {$type}"
                );
        }

        return $source->resolve(
            $documentData,
            $config
        );
    }
}