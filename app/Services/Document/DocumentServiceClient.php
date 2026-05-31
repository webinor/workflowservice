<?php

namespace App\Services\Document;

use Illuminate\Support\Facades\Http;

class DocumentServiceClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl =
            rtrim(env('DOCUMENT_SERVICE_URL'), '/');
    }

    /**
     * =========================================
     * Génération documents mission
     * =========================================
     */
    public function generateMissionDocuments(
        int $documentId,
        int $instanceId,
        string $context
    ) {

        $response = Http::withToken(
                request()->bearerToken()
            )
            ->acceptJson()
            ->post(

                config("services.document_service.base_url")
                . "/missions/generate",

                [
                    "document_id" => $documentId,
                    "instance_id" => $instanceId,
                    "context" => $context
                        ?? "logistics_validation",
                ]
            );

        if (!$response->successful()) {

            throw new \Exception(
                "DocumentService error: "
                . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * =========================================
     * Récupérer un document
     * =========================================
     */
    public function getDocument(
        int $documentId
    ): array {

        $response = Http::withToken(
                request()->bearerToken()
            )
            ->acceptJson()
            ->get(

                config("services.document_service.base_url")
                . "/{$documentId}"
            );

        if (!$response->successful()) {

            throw new \Exception(
                "DocumentService error: "
                . $response->body()
            );
        }

        return $response->json();
    }
}