<?php

namespace App\Services\Document;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocumentServiceClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            env("DOCUMENT_SERVICE_URL"),
            "/"
        );
    }

    /**
     * =========================================
     * Génération documents mission
     * =========================================
     */
    public function generateMissionDocuments(
        $documentUuid,
        int $instanceId,
        string $context
    ) {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config("services.document_service.base_url") .
                    "/missions/generate",
                [
                    "document_uuid" => $documentUuid,
                    "instance_id" => $instanceId,
                    "context" => $context ?? "logistics_validation",
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                "DocumentService error: " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * =========================================
     * Déduction des jours de congé
     * =========================================
     */
    public function deductLeaveDays(
        $documentUuid,
        int $instanceId,
        string $context = 'workflow_validation'
    ) {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config('services.document_service.base_url') .
                    '/leave-balances/deduct',
                [
                    'document_uuid' => $documentUuid,
                    'instance_id' => $instanceId,
                    'context' => $context,
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                'DocumentService error: ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * =========================================
     * Génération documents congé
     * =========================================
     */
    public function generateLeaveDocuments(
        string $documentUuid,
        int $instanceId,
        array $config
    ) {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config('services.document_service.base_url') .
                    '/leave/generate',
                [
                    'document_uuid' => $documentUuid,
                    'instance_id' => $instanceId,
                    'config' => $config,
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                'DocumentService error: ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * =========================================
     * Récupérer un document
     * =========================================
     */
    public function getDocument(string $documentUuid): array
    {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->get(
                config("services.document_service.base_url") .
                    "/{$documentUuid}"
            );

        if (!$response->successful()) {
            throw new Exception(
                "DocumentService error: " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * =========================================
     * Apposer les signatures sur les pièces
     * justificatives d'une fiche de régularisation
     * =========================================
     */
    public function applyRegularizationSupportingDocumentSignatures(
        string $documentUuid
    ) {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config("services.document_service.base_url") .
                    "/{$documentUuid}/regularization/supporting-documents/apply-signatures"
            );

        if (!$response->successful()) {
            throw new Exception(
                "DocumentService error: " . $response->body()
            );
        }

        // throw new Exception(json_encode($response->json()), 1);
        

        return $response->json();
    }

    /**
     * =========================================
     * Récupérer les types de documents
     * =========================================
     */
    public function getDocumentTypesByIds(
        array $documentUuids,
        ?string $token = null
    ): array {
        $url = config("services.document_service.base_url");

        $http = Http::timeout(120)
            ->acceptJson();

        if ($token) {

            $http = $http->withHeaders([
                'X-Service-Token' => $token,
            ]);

        } else {

            $http = $http->withToken(
                request()->bearerToken()
            );
        }

        $response = $http->post(
            "{$url}/types-by-ids",
            [
                "ids" => $documentUuids,
            ]
        );

        if (!$response->ok()) {
            throw new Exception(
                "Document service error : " .
                $response->body()
            );
        }

        return $response->json("data") ?? [];
    }

    public function exportExcel(
    array $documentIds,
    string $documentType,
    array $columns,
    array $workflowMetadata,
    Request $request
) {
    $response = Http::withToken(
        $request->bearerToken()
    )
        ->acceptJson()
        ->post(
            config('services.document_service.base_url')
            . '/export/excel',
            [
                'document_ids' => $documentIds,
                'document_type' => $documentType,
                'columns' => $columns,
                'workflow_metadata' => $workflowMetadata,
            ]
        );

    if (!$response->successful()) {
        throw new \Exception(
            $response->body(),
            $response->status()
        );
    }

    return $response;
}



public function fetchDocuments(
    $documentIds,
    array $documentTypes,
    ?array $filters,
    Request $request,
    bool $isStat = false,
    bool $shouldEnrich = true
): array {

    // Début de la mesure globale de la méthode.
    $methodStart = microtime(true);

    $ids = $documentIds->toArray();

    $url = config("services.document_service.base_url") . "/by-ids";

    $queryParams = [
        "ids" => $ids,
        "documentTypes" => $documentTypes,
        "filters" => $filters,
        "shouldEnrich" => $shouldEnrich,
        "isStat" => $isStat,
    ];

    /*
     * On conserve les informations utiles au diagnostic,
     * sans journaliser les identifiants ni les filtres complets.
     */
    logger()->info("DOCUMENT CLIENT - REQUETE BY IDS", [
        "url" => $url,
        "ids_count" => count($ids),
        "document_types" => $documentTypes,
        "filters_count" => is_array($filters)
            ? count($filters)
            : 0,
        "filter_keys" => is_array($filters)
            ? array_keys($filters)
            : [],
        "is_stat" => $isStat,
        "should_enrich" => $shouldEnrich,
    ]);

    /*
     * Étape 1 : mesurer l'appel HTTP.
     *
     * Cette durée comprend notamment l'envoi de la requête,
     * le traitement côté serveur et la réception de la réponse.
     */
    $httpStart = microtime(true);

    $response = Http::withToken($request->bearerToken())
        ->acceptJson()
        ->get($url, $queryParams);

    $httpDurationMs = round(
        (microtime(true) - $httpStart) * 1000,
        2
    );

    /*
     * Étape 2 : mesurer le décodage JSON.
     *
     * On décode la réponse une seule fois afin d'éviter
     * de répéter inutilement cet appel dans les logs et le retour.
     */
    $decodeStart = microtime(true);

    $decodedResponse = $response->json();

    $decodeDurationMs = round(
        (microtime(true) - $decodeStart) * 1000,
        2
    );

    $totalDurationMs = round(
        (microtime(true) - $methodStart) * 1000,
        2
    );

    /*
     * Étape 3 : journaliser les mesures.
     *
     * Ne pas journaliser le corps complet de la réponse :
     * il peut contenir des informations personnelles ou financières.
     */
    logger()->info("DOCUMENT CLIENT - PERF BY IDS", [
        "http_status" => $response->status(),
        "content_type" => $response->header("Content-Type"),

        "ids_count" => count($ids),
        "document_types" => $documentTypes,

        "http_duration_ms" => $httpDurationMs,
        "json_decode_duration_ms" => $decodeDurationMs,
        "total_duration_ms" => $totalDurationMs,

        "response_count" => is_countable($decodedResponse)
            ? count($decodedResponse)
            : null,

        "response_size_bytes" => strlen($response->body()),

        "is_stat" => $isStat,
        "should_enrich" => $shouldEnrich,
    ]);

    /*
     * Étape 4 : conserver le comportement existant.
     */
    if ($response->ok()) {
        return $decodedResponse;
    }

    throw new Exception(
        json_encode($response->body()),
        1
    );
}

    public function OldfetchDocuments(
    $documentIds,
    array $documentTypes,
    ?array $filters,
    Request $request,
    bool $isStat = false,
    bool $shouldEnrich = true
): array {

    $ids = $documentIds->toArray();

    $url = config("services.document_service.base_url") . "/by-ids";

    $queryParams = [
        "ids" => $ids,
        "documentTypes" => $documentTypes,
        "filters" => $filters,
        "shouldEnrich" => $shouldEnrich,
        "isStat" => $isStat,
    ];

    logger()->info("DOCUMENT CLIENT - REQUETE BY IDS", [
        "url" => $url,
        "ids_count" => count($ids),
        "ids" => $ids,
        "document_types" => $documentTypes,
        "filters" => $filters,
        "is_stat" => $isStat,
        "should_enrich" => $shouldEnrich,
    ]);

    $response = Http::withToken($request->bearerToken())
        ->acceptJson()
        ->get($url, $queryParams);

    logger()->info("DOCUMENT CLIENT - REPONSE BY IDS", [
        "http_status" => $response->status(),
        "response_is_json" => $response->header("Content-Type"),
        "response_count" => count($response->json() ?? []),
        "response_body" => $response->body(),
    ]);

    if ($response->ok()) {
        return $response->json();
    }

    throw new Exception(
        json_encode($response->body()),
        1
    );
}

}