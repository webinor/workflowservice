<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;

class WorkflowPermissionService
{


    public function checkPermissions(array $rawDocuments, $request)
    {
        // On récupère le userId (par ex. du document ou du contexte connecté)
        $userId = $rawDocuments["documents"][0]["created_by"];

        // On génère le payload (grâce à la fonction qu’on a faite avant)
        $payload = $this->transformToPayload(
            $rawDocuments,
            $rawDocuments["role_id"],
            ["view", "validate"]
        );
        //$payload = $this->transformToPayload($rawDocuments, $userId, ['view', 'validate']);

        // Appel vers userservice
        $response = Http::withToken($request->bearerToken())
            ->acceptJson()
            ->post(
                config("services.user_service.base_url") .
                    "/permissions/check-batch-role",
                $payload
            );
        // ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch', $payload);

        if ($response->failed()) {
            throw new \Exception(
                "Erreur lors de la vérification des permissions du workflow : " .
                    $response->body()
            );
        }

        return $response->json();
    }

    public function checkPermissions2(array $rawDocuments, $request)
    {
        // On récupère le userId (par ex. du document ou du contexte connecté)
        $userId = $rawDocuments["documents"][0]["created_by"];

        // On génère le payload (grâce à la fonction qu’on a faite avant)
        //$payload = $this->transformToPayload($rawDocuments, $rawDocuments['role_id'], ['view', 'validate']);
        $payload = $this->transformToPayload2(
            $rawDocuments,
            $rawDocuments["user_id"],
            ["view_own", "view_all" , "view_department"] //, "validate"]
        );

        // Appel vers userservice
        $response = Http::withToken($request->bearerToken())
            ->acceptJson()
            ->post(
                config("services.user_service.base_url") .
                    "/permissions/check-batch",
                $payload
            );
        // ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch', $payload);

        if ($response->failed()) {
            throw new \Exception(
                "Erreur lors de la vérification des permissions du workflow : " .
                    $response->body()
            );
        }

        return $response->json();
    }

    function transformToPayload(
        array $raw,
        int $roleId,
        array $actions = ["view", "validate"]
    ) {
        return [
            "roleId" => $roleId,
            // 'userId' => $userId,
            "documents" => collect($raw["documents"] ?? [])
                ->map(function ($doc) {
                    return [
                        "doc_id" => $doc["id"],
                        "id" => $doc["document_type_id"],
                        "type" => $doc["document_type"]["name"] ?? "Unknown",
                    ];
                })
                ->toArray(),
            "actions" => $actions,
        ];
    }

    function transformToPayload2(
        array $raw,
        int $userId,
        array $actions = ["view", "validate"]
    ) {
        return [
            //  'roleId' => $roleId,
            "userId" => $userId,
            "documents" => collect($raw["documents"] ?? [])
                ->map(function ($doc) {
                    return [
                        "doc_id" => $doc["id"],
                        "id" => $doc["document_type_id"],
                        "type" => $doc["document_type"]["name"] ?? "Unknown",
                    ];
                })
                ->toArray(),
            "actions" => $actions,
        ];
    }
}
