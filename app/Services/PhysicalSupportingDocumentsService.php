<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PhysicalSupportingDocumentsService
{
    /**
     * URL de base du Document Service.
     *
     * Exemple :
     * http://127.0.0.1:8091
     */
    protected string $baseUrl;

    /**
     * Endpoint du Document Service chargé de marquer
     * les justificatifs physiques comme transmis.
     */
    protected string $endpoint;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) config('services.document_service.base_url', ''),
            '/'
        );

        $this->endpoint = "/regularization-receipts/confirm-physical-transmission";
        
        // (string) config(
        //     'services.document_service.mark_physical_supporting_documents_received_path',
        //     '/api/documents/physical-supporting-documents/received'
        // );
    }

    /**
     * Marque les justificatifs physiques comme transmis
     * dans le Document Service.
     *
     * Cette méthode est réutilisable :
     *
     * - après la signature du bénéficiaire ;
     * - après la clôture du workflow par la trésorerie.
     *
     * L'appel est considéré comme réussi uniquement si :
     *
     * - le Document Service répond avec un statut HTTP 2xx ;
     * - sa réponse ne contient pas explicitement success = false.
     *
     * @param int    $documentId
     * @param string $documentUuid
     * @param array  $actor
     * @param string $source
     * @param string $transactionTypeCode
     *
     * @return array
     */
    public function markAsReceived(
        int $documentId,
        string $documentUuid,
        array $actor,
        string $source,
        string $transactionTypeCode
    ): array {
        $startedAt = microtime(true);

        $url = $this->baseUrl
            . "/$documentId/"
            . ltrim($this->endpoint, '/');

        $context = [
            'document_id' => $documentId,
            'document_uuid' => $documentUuid,
            'source' => $source,
            'transaction_type_code' => $transactionTypeCode,
            'actor_id' => $actor['id'] ?? null,
        ];

        /*
         * ---------------------------------------------------------
         * 1. Vérification de la configuration
         * ---------------------------------------------------------
         */
        if ($this->baseUrl === '') {
            Log::error(
                '[PhysicalSupportingDocuments] Document Service URL manquante.',
                $context
            );

            return [
                'success' => false,
                'message' => 'URL du Document Service non configurée.',
                'status' => null,
            ];
        }

        /*
         * ---------------------------------------------------------
         * 2. Préparation de la requête HTTP
         * ---------------------------------------------------------
         */
        $payload = [
            'document_id' => $documentId,
            'document_uuid' => $documentUuid,

            'received_by' => $actor['id'] ?? null,
            'received_by_name' => $actor['name'] ?? null,
            'received_by_type' => $actor['type'] ?? null,
            'received_by_role' => $actor['role'] ?? null,

            'source' => $source,
            'transaction_type_code' => $transactionTypeCode,
        ];

        Log::info(
            '[PhysicalSupportingDocuments] Début de la confirmation de transmission.',
            $context
        );

        try {
            /*
             * -----------------------------------------------------
             * 3. Appel du Document Service
             * -----------------------------------------------------
             *
             * Le token est facultatif ici, selon le mécanisme
             * d'authentification interservices de ton application.
             *
             * Si le Document Service exige un token, configure :
             *
             * services.document_service.token
             */
            $http = Http::acceptJson()
                ->asJson()
                ->timeout(15);

          $bearerToken = request()->bearerToken();
$serviceToken = config('services.document_service.token');

if (!empty($bearerToken)) {
    // Authentification avec le token Bearer de la requête entrante.
    $http = $http->withToken($bearerToken);

    $authType = 'BEARER_TOKEN';
} elseif (!empty($serviceToken)) {
    // Authentification avec le token de service.
    $http = $http->withHeaders([
        'X-SERVICE-TOKEN' => $serviceToken,
    ]);

    $authType = 'SERVICE_TOKEN';
} else {
    // Aucun mécanisme d'authentification disponible.
    Log::error('DocumentServiceClient : aucun token disponible.', [
        'service' => 'document_service',
        'auth_type' => 'NONE',
    ]);

    throw new \RuntimeException(
        'Impossible d’appeler le Document Service : aucun token disponible.'
    );
}

// Informations de diagnostic sans exposer le token.
Log::debug('DocumentServiceClient : authentification configurée.', [
    'service' => 'document_service',
    'auth_type' => $authType,
    'token_present' => true,
]);

           

            $response = $http->post($url, $payload);

            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                2
            );

            /*
             * -----------------------------------------------------
             * 4. Vérification de la réponse
             * -----------------------------------------------------
             */
            $body = $response->json();

            $applicationRejected = is_array($body)
                && array_key_exists('success', $body)
                && $body['success'] === false;

            if (!$response->successful() || $applicationRejected) {
                Log::error(
                    '[PhysicalSupportingDocuments] Échec de la confirmation.',
                    array_merge($context, [
                        'http_status' => $response->status(),
                        'duration_ms' => $durationMs,
                        'url' => $url,
                        'authType' => $authType,
                        'response_message' => is_array($body)
                            ? ($body['message'] ?? null)
                            : null,
                    ])
                );

                return [
                    'success' => false,
                    'message' => is_array($body)
                        ? ($body['message'] ?? 'Échec du Document Service.')
                        : 'Réponse HTTP invalide du Document Service.',
                    'status' => $response->status(),
                ];
            }

            /*
             * -----------------------------------------------------
             * 5. Confirmation du succès
             * -----------------------------------------------------
             */
            Log::info(
                '[PhysicalSupportingDocuments] Transmission confirmée.',
                array_merge($context, [
                    'http_status' => $response->status(),
                    'duration_ms' => $durationMs,
                ])
            );

            return [
                'success' => true,
                'message' => 'Transmission des justificatifs confirmée.',
                'status' => $response->status(),
            ];
        } catch (Throwable $exception) {
            /*
             * -----------------------------------------------------
             * 6. Erreur réseau, timeout ou autre exception
             * -----------------------------------------------------
             */
            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                2
            );

            Log::error(
                '[PhysicalSupportingDocuments] Exception pendant l’appel.',
                array_merge($context, [
                    'duration_ms' => $durationMs,
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                ])
            );

            return [
                'success' => false,
                'message' => 'Impossible de confirmer la transmission des justificatifs.',
                'status' => null,
            ];
        }
    }
}