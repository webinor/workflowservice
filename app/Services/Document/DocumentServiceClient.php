<?php

namespace App\Services\Document;

use Illuminate\Support\Facades\Http;

class DocumentServiceClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('DOCUMENT_SERVICE_URL'), '/');
    }

    /**
     * Génère les documents de mission (LM, OM, Fiche régularisation)
     */
    public function generateMissionDocuments(int $documentId , int $instanceId, string $context )
    {
        // $response = Http::timeout(15)->post(
        //     $this->baseUrl . "/api/documents/missions/generate",
        //     [
        //         "instance_id" => $instanceId,
        //         "context" => $context ?? "logistics_validation",
        //     ]
        // );

      

         $response = Http::withToken(request()->bearerToken())
                    ->acceptJson()
                    ->post(config("services.document_service.base_url")."/missions/generate",
            [
                "document_id" => $documentId,
                "instance_id" => $instanceId,
                "context" => $context ?? "logistics_validation",
            ]
                        );

        if (!$response->successful()) {
            throw new \Exception(
                "DocumentService error: " . $response->body()
            );
        }

        return $response->json();
    }
}