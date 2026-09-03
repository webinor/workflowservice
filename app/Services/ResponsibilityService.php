<?php

namespace App\Services;

class ResponsibilityService
{
    /**
     * Vérifie si les responsabilités contiennent
     * au moins un des codes demandés.
     */
    public function hasAnyCode(array $responsibilities, array $codes): bool
    {
        if (empty($responsibilities) || empty($codes)) {
            return false;
        }

        $codes = array_map('strtoupper', $codes);

        foreach ($responsibilities as $responsibility) {
            $code = $responsibility['code'] ?? null;

            if ($code && in_array(strtoupper($code), $codes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si les responsabilités contiennent
     * tous les codes demandés.
     */
    public function hasAllCodes(array $responsibilities, array $codes): bool
    {
        if (empty($codes)) {
            return true;
        }

        $availableCodes = array_map(
            'strtoupper',
            array_column($responsibilities, 'code')
        );

        foreach ($codes as $code) {
            if (!in_array(strtoupper($code), $availableCodes, true)) {
                return false;
            }
        }

        return true;
    }
}