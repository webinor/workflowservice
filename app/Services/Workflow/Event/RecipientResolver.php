<?php

namespace App\Services\Workflow\Event;

use App\Services\HttpClientService;
use Exception;

class RecipientResolver
{
    protected HttpClientService $httpClient;

    public function __construct()
    {
        // $this->httpClient = $httpClient;
        $this->httpClient = HttpClientService::service("employee");
    }

    /**
     * Résout les audiences en véritables destinataires.
     *
     * Exemple d'entrée :
     *
     * [
     *     "email" => [
     *         "to" => [8, 12],
     *         "cc" => [15],
     *         "bcc" => []
     *     ]
     * ]
     *
     * Exemple de sortie :
     *
     * [
     *     "email" => [
     *         "to" => [
     *             [
     *                 "id" => 8,
     *                 "nom" => "MELACHI",
     *                 "prenom" => "Rufus",
     *                 "civilite" => "M.",
     *                 "email" => "...",
     *                 "signatureUrl" => "..."
     *             ]
     *         ],
     *         "cc" => [],
     *         "bcc" => []
     *     ]
     * ]
     */
    public function resolve(array $audiences): array
    {
        /**
         * =========================================================
         * 1. Récupérer tous les IDs présents dans les audiences
         * =========================================================
         */

        $employeeIds = $this->extractEmployeeIds($audiences);

        // throw new Exception(json_encode($employeeIds), 1);


        if (empty($employeeIds)) {
            return $this->emptyAudiences($audiences);
        }

        /**
         * =========================================================
         * 2. Récupération BATCH des employés
         * =========================================================
         */

        $employees = $this->getEmployees($employeeIds);


        /**
         * =========================================================
         * 3. Reconstituer les audiences
         * =========================================================
         */
        
        // throw new Exception(json_encode($this->hydrateAudiences(
        //     $audiences,
        //     $employees
        // )), 1);

        return $this->hydrateAudiences(
            $audiences,
            $employees
        );
    }

    /**
     * Récupère tous les IDs employés présents dans :
     *
     * to / cc / bcc
     *
     * de tous les channels.
     */
    protected function extractEmployeeIds(array $audiences): array
    {
        $employeeIds = [];

        foreach ($audiences as $channel => $audience) {

            foreach (["to", "cc", "bcc"] as $type) {

                $recipients = $audience[$type] ?? [];

                if (!is_array($recipients)) {
                    continue;
                }

                foreach ($recipients as $recipient) {

                    // Nouveau format :
                    // {
                    //     "recipient_id": 5,
                    //     "recipient_email": "...",
                    //     "recipient_phone": null
                    // }
                    if (is_array($recipient)) {

                        $employeeId = $recipient["recipient_id"] ?? null;

                        if (
                            $employeeId !== null &&
                            $employeeId !== "" &&
                            is_numeric($employeeId)
                        ) {
                            $employeeIds[] = (int) $employeeId;
                        }

                        continue;
                    }

                    // Ancien format : directement un ID
                    if (
                        $recipient !== null &&
                        $recipient !== "" &&
                        is_numeric($recipient)
                    ) {
                        $employeeIds[] = (int) $recipient;
                    }
                }
            }
        }

        return array_values(array_unique($employeeIds));
    }

    /**
     * Récupère les employés en une seule requête
     * auprès du User/Employee Service.
     */
    protected function getEmployees(array $employeeIds): array
    {
        $response = $this->httpClient
            ->service("employee")
            ->get("employees-by-ids", [
                "ids" => implode(",", $employeeIds),
            ]);


        if (!$response["success"]) {

            throw new Exception(
                "Impossible de récupérer les destinataires. " .
                ($response["error"] ?? "Erreur inconnue")
            );
        }

        $employees = $response["data"]["data"] ?? [];

        if (!is_array($employees)) {
            return [];
        }

        /**
         * Indexation par employee_id
         *
         * [8 => [...], 12 => [...]]
         */
        return collect($employees)
            ->filter(function ($employee) {
                return isset($employee["id"]);
            })
            ->keyBy("id")
            ->toArray();
    }

/**
 * Remplace les IDs présents dans les audiences
 * par les informations complètes des employés.
 *
 * Supporte :
 *
 * Ancien format :
 * [
 *     "to" => [5, 7]
 * ]
 *
 * Nouveau format :
 * [
 *     "to" => [
 *         [
 *             "recipient_id" => 5,
 *             "recipient_email" => "therese788@cas.com",
 *             "recipient_phone" => null
 *         ]
 *     ]
 * ]
 */
protected function hydrateAudiences(
    array $audiences,
    array $employees
): array {
    $resolved = [];

    foreach ($audiences as $channel => $audience) {

        $resolved[$channel] = [
            "to" => [],
            "cc" => [],
            "bcc" => [],
        ];

        foreach (["to", "cc", "bcc"] as $type) {

            $recipients = $audience[$type] ?? [];

            if (!is_array($recipients)) {
                continue;
            }

            foreach ($recipients as $recipient) {

                /*
                |--------------------------------------------------------------------------
                | Récupération de l'ID employé
                |--------------------------------------------------------------------------
                */

                if (is_array($recipient)) {

                    // Nouveau format
                    $employeeId = $recipient["recipient_id"] ?? null;

                } else {

                    // Ancien format : ID directement
                    $employeeId = $recipient;
                }

                /*
                |--------------------------------------------------------------------------
                | Validation
                |--------------------------------------------------------------------------
                */

                if (
                    $employeeId === null ||
                    $employeeId === "" ||
                    !is_numeric($employeeId)
                ) {
                    continue;
                }

                $employeeId = (int) $employeeId;

                /*
                |--------------------------------------------------------------------------
                | Recherche de l'employé
                |--------------------------------------------------------------------------
                */

                if (!isset($employees[$employeeId])) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Ajout du recipient enrichi
                |--------------------------------------------------------------------------
                */

                $resolved[$channel][$type][] = $employees[$employeeId];
            }
        }
    }

    return $resolved;
}

    /**
     * Retourne une structure vide tout en conservant
     * les channels fournis par l'audience resolver.
     */
    protected function emptyAudiences(array $audiences): array
    {
        $resolved = [];

        foreach ($audiences as $channel => $audience) {

            $resolved[$channel] = [
                "to" => [],
                "cc" => [],
                "bcc" => [],
            ];
        }

        return $resolved;
    }
}