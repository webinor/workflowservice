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