<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;
use Illuminate\Support\Facades\Log;

class GenerateLeaveDocumentsHandler
    implements WorkflowEventHandlerInterface
{
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        $this->documentClient = $documentClient;
    }

    /**
     * Exécute la génération des documents liés à une demande d'absence.
     *
     * Génère actuellement :
     * - la demande d'absence validée ;
     * - la lettre de mise en congé.
     *
     * Les documents générés sont ensuite préparés comme pièces jointes
     * pour le service de notification.
     *
     * @param string $documentUuid
     * @param mixed  $instance
     * @param array  $documentData
     * @param array  $config
     *
     * @return array
     */
    public function execute(
        string $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        /*
         * =========================================================
         * CONTEXTE
         * =========================================================
         *
         * Conservation de la logique existante.
         */
        $context = $config['context']
            ?? ['leave_validated'];

        Log::info(
            'GenerateLeaveDocumentsHandler: début de génération des documents d’absence.',
            [
                'document_uuid' => $documentUuid,
                'instance_id' => $instance->id ?? null,
                'context' => $context,
                'document_type' =>
                    $documentData['document_type']['slug'] ?? null,
            ]
        );

        /*
         * =========================================================
         * LOG DE L'ACTION UTILISATEUR
         * =========================================================
         */
        Log::info(
            "L'utilisateur a généré les documents d'absence.",
            [
                'document_id' =>
                    $documentData['uuid'] ?? $documentUuid,

                'employee_id' =>
                    $documentData['actor_id'] ?? null,

                'generated_by' =>
                    auth()->id(),
            ]
        );

        /*
         * =========================================================
         * GÉNÉRATION DES DOCUMENTS
         * =========================================================
         *
         * Le service documentaire génère actuellement deux
         * documents :
         *
         * 1. La demande d'absence validée
         * 2. La lettre de mise en congé
         */
        Log::info(
            'GenerateLeaveDocumentsHandler: appel du service documentaire.',
            [
                'document_uuid' => $documentUuid,
                'instance_id' => $instance->id ?? null,
                'contexts' => [
                    'leave_request_validated',
                    // 'leave_order',
                ],
                'executed_at' => $instance->executed_at ?? null,
            ]
        );

        $result = $this->documentClient->generateLeaveDocuments(
            $documentUuid,
            $instance->id,
            [
                'data' => [
                    'executed_at' => $instance->executed_at,
                ],

                'contexts' => [
                    'leave_request_validated',
                    // 'leave_order',
                ],
            ]
        );

        /*
         * =========================================================
         * RÉSULTAT DE LA GÉNÉRATION
         * =========================================================
         */
        Log::info(
            'GenerateLeaveDocumentsHandler: documents générés.',
            [
                'document_uuid' => $documentUuid,
                'instance_id' => $instance->id ?? null,
                'success' => $result['success'] ?? null,
                'generated_documents_count' =>
                    count($result['documents'] ?? []),
                'documents' => $result['documents'] ?? [],
            ]
        );

        /*
         * =========================================================
         * PRÉPARATION DES PIÈCES JOINTES
         * =========================================================
         *
         * L'API retourne maintenant plusieurs documents :
         *
         * [
         *     'documents' => [
         *         [
         *             'type' => 'leave_request_validated',
         *             'file_name' => '...',
         *             'url' => '...',
         *         ],
         *         [
         *             'type' => 'leave_order',
         *             'file_name' => '...',
         *             'url' => '...',
         *         ],
         *     ]
         * ]
         *
         * NotificationService attend :
         *
         * [
         *     [
         *         'id' => ...,
         *         'title' => ...,
         *         'download_url' => ...
         *     ]
         * ]
         *
         */
        $attachments = [];

        foreach (
            data_get($result, 'documents', [])
            as $generatedDocument
        ) {

            /*
             * -----------------------------------------------------
             * Informations du document généré
             * -----------------------------------------------------
             */
            $fileId =
                $generatedDocument['file_id']
                ?? null;

            $fileName =
                $generatedDocument['file_name']
                ?? null;

            $downloadUrl =
                $generatedDocument['url']
                ?? null;

            /*
             * -----------------------------------------------------
             * Log du document généré
             * -----------------------------------------------------
             */
            Log::info(
                'GenerateLeaveDocumentsHandler: préparation de la pièce jointe.',
                [
                    'document_uuid' => $documentUuid,
                    'type' =>
                        $generatedDocument['type']
                        ?? null,

                    'file_id' => $fileId,

                    'file_name' => $fileName,

                    'download_url' => $downloadUrl,
                ]
            );

            /*
             * -----------------------------------------------------
             * Ajout de la pièce jointe
             * -----------------------------------------------------
             *
             * On ajoute uniquement un document disposant
             * d'au moins un identifiant ou d'une URL.
             */
            if ($fileId || $downloadUrl) {

                $attachments[] = [
                    'id' => $fileId,
                    'title' => $fileName,
                    'download_url' => $downloadUrl,
                ];

                Log::info(
                    'GenerateLeaveDocumentsHandler: pièce jointe ajoutée.',
                    [
                        'document_uuid' => $documentUuid,
                        'type' =>
                            $generatedDocument['type']
                            ?? null,

                        'file_name' => $fileName,
                    ]
                );

            } else {

                Log::warning(
                    'GenerateLeaveDocumentsHandler: document ignoré car aucun fichier ou URL disponible.',
                    [
                        'document_uuid' => $documentUuid,
                        'type' =>
                            $generatedDocument['type']
                            ?? null,

                        'file_name' => $fileName,
                        'file_id' => $fileId,
                        'download_url' => $downloadUrl,
                    ]
                );
            }
        }

        /*
         * =========================================================
         * LOG FINAL DES PIÈCES JOINTES
         * =========================================================
         */
        Log::info(
            'GenerateLeaveDocumentsHandler: préparation des pièces jointes terminée.',
            [
                'document_uuid' => $documentUuid,
                'attachments_count' => count($attachments),
                'attachments' => $attachments,
            ]
        );

        /*
         * =========================================================
         * DONNÉES RETOURNÉES AU WORKFLOW
         * =========================================================
         */
        Log::info(
            'GenerateLeaveDocumentsHandler: préparation du résultat final.',
            [
                'document_uuid' => $documentUuid,
                'reference' =>
                    $documentData['reference']
                    ?? null,

                'leave_type' =>
                    $documentData['absence_request']['leave_type']['name']
                    ?? $documentData['absence_request']['type']
                    ?? null,

                'departure_date' =>
                    $documentData['absence_request']['departure_date']
                    ?? null,

                'return_date' =>
                    $documentData['absence_request']['return_date']
                    ?? null,
            ]
        );

        /*
         * =========================================================
         * RÉSULTAT
         * =========================================================
         */
        return [
            'data' => [
                'actor' =>
                    $documentData['actor_details']['nom']
                    ?? '',

                'reference' =>
                    $documentData['reference']
                    ?? '',

                'leave_type' =>
                    $documentData['absence_request']['leave_type']['name']
                    ?? $documentData['absence_request']['type']
                    ?? '',

                'departure_date' =>
                    $documentData['absence_request']['departure_date']
                    ?? '',

                'return_date' =>
                    $documentData['absence_request']['return_date']
                    ?? '',

                'document_title' =>
                    $documentData['title']
                    ?? 'Demande de congé',

                'owner_name' =>
                    $documentData['actor_details']['nom']
                    ?? '',

                'document_url' =>
                    config('services.frontend_service.base_url'),
            ],

            /*
             * Résultat brut retourné par le service documentaire.
             */
            'results' => $result,

            /*
             * Documents générés utilisés comme pièces jointes
             * pour la notification.
             */
            'attachments' => $attachments,
        ];
    }
}
