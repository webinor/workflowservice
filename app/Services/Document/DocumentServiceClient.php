<?php

namespace App\Services\Document;

use Exception;
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
        $documentUuid,
        int $instanceId,
        string $context
    ) {
        $response = Http::withToken(request()->bearerToken())
            ->acceptJson()
            ->post(
                config('services.document_service.base_url') .
                    '/leave/generate',
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

        $http = Http::timeout(20)
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
}