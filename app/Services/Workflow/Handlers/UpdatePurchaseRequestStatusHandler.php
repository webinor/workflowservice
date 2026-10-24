<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UpdatePurchaseRequestStatusHandler implements WorkflowEventHandlerInterface
{
    /**
     * Demande au Document Service de recalculer le statut
     * d'une demande d'achat.
     *
     * Le statut final est déterminé exclusivement par le Document Service.
     *
     * @param string $documentUuid
     * @param mixed  $instance
     * @param array  $documentData
     * @param array  $config
     *
     * @return array
     */
    public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {
        $baseUrl = rtrim(
            (string) config('services.document_service.base_url'),
            '/'
        );

        if ($baseUrl === '') {
            throw new RuntimeException(
                'La configuration DOCUMENT_SERVICE_URL est manquante.'
            );
        }

        /*
         * L'identifiant de la demande d'achat doit être transmis
         * dans les données du document.
         */
        $purchaseRequestId = $documentData['purchase_request_id']
            ?? ($documentData['purchase_request']['id'] ?? null);

        if (!$purchaseRequestId) {
            throw new RuntimeException(
                'Impossible de mettre à jour le statut : '
                . 'purchase_request_id est absent des données du document.'
            );
        }

        /*
         * On transmet le contexte métier, pas le statut cible.
         * Le Document Service décide du statut à appliquer.
         */
        $payload = [
            'document_uuid' => $documentUuid,
            'workflow_instance_id' => isset($instance->id)
                ? $instance->id
                : null,
            'workflow_instance_step_status' => $instance->status// $documentData['workflow_status']
                ?? null,
            'workflow_action' => "DOCUMENT_VALIDATED"// $documentData['workflow_action']
                ?? null,
        ];

        /*
         * On récupère le token de la requête entrante pour l'appel
         * interservices, conformément au mécanisme d'authentification
         * actuel de l'application.
         */
        $token = request()->bearerToken();

        $http = Http::acceptJson()->timeout(15);

        if ($token) {
            $http = $http->withToken($token);
        }

        $url = $baseUrl
            . '/purchase-requests/'
            . $purchaseRequestId
            . '/status/recalculate';

        try {
            $response = $http->post($url, $payload);

            if ($response->failed()) {
                Log::error(
                    'Échec de la mise à jour du statut de la demande d’achat.',
                    [
                        'purchase_request_id' => $purchaseRequestId,
                        'document_uuid' => $documentUuid,
                        'http_status' => $response->status(),
                        'response' => $response->body(),
                    ]
                );

                throw new RuntimeException(
                    'Le Document Service a refusé la mise à jour du statut. '
                    . 'HTTP ' . $response->status()
                );
            }

            $result = $response->json();

            Log::info(
                'Statut de la demande d’achat traité par le Document Service.',
                [
                    'purchase_request_id' => $purchaseRequestId,
                    'document_uuid' => $documentUuid,
                    'result' => $result,
                ]
            );

            return [
                'data' => [
                    'purchase_request_status_update' => $result,
                ],
                'attachments' => [],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error(
                'Document Service inaccessible lors de la mise à jour '
                . 'du statut de la demande d’achat.',
                [
                    'purchase_request_id' => $purchaseRequestId,
                    'document_uuid' => $documentUuid,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Impossible de contacter le Document Service.',
                0,
                $e
            );
        }
    }
}