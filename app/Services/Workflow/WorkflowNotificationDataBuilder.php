<?php

namespace App\Services\Workflow;

use App\Services\User\UserServiceClient;

class WorkflowNotificationDataBuilder
{
    protected UserServiceClient $userService;

    public function __construct(
        UserServiceClient $userService
    ) {
        $this->userService = $userService;
    }

    /**
     * Résout un utilisateur depuis UserService.
     *
     * Si un employee est disponible, celui-ci est retourné.
     * Sinon les données utilisateur sont retournées.
     */
    public function resolveUser(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        $userData = $this->userService->find(
            $userId
        );

        if (!$userData) {
            return null;
        }

        return isset($userData['employee'])
            ? $userData['employee']
            : $userData;
    }

    /**
     * Retourne le nom complet d'un utilisateur.
     */
    public function getUserName(?array $user): string
    {
        if (!$user) {
            return '';
        }

        $name = isset($user['nom'])
            ? $user['nom']
            : (
                isset($user['name'])
                    ? $user['name']
                    : ''
            );

        if (
            isset($user['prenom']) &&
            !empty($user['prenom'])
        ) {
            $name .= ' ' . $user['prenom'];
        }

        return trim($name);
    }

    /**
     * Retourne l'adresse email d'un utilisateur.
     */
    public function getUserEmail(?array $user): string
    {
        if (!$user) {
            return '';
        }

        return isset($user['email'])
            ? $user['email']
            : '';
    }

    /**
 * Construit l'URL frontend d'un document
 * selon son type et son contexte.
 *
 * Contextes possibles :
 *
 * MY_DOCUMENTS
 * TO_VALIDATE
 */
public function buildDocumentUrl(
    string $documentUuid,
    ?string $context = null,
    ?string $documentTypeCode = null
): string {

// throw new \Exception($documentTypeCode, 1);
// throw new \Exception($context, 1);


    $routes = [

        'MY_DOCUMENTS' => [

            'papier-taxi' =>
                "/mes-papiers-taxi/{$documentUuid}",

            'taxi_paper' =>
                "/mes-papiers-taxi/{$documentUuid}",

            'note-de-frais' =>
                "/mes-notes-de-frais/{$documentUuid}",

            'fee_note' =>
                "/mes-notes-de-frais/{$documentUuid}",

            'fiche-a-regulariser' =>
                "/mes-fiches-a-regulariser/{$documentUuid}",

            'regularization_sheet' =>
                "/mes-fiches-a-regulariser/{$documentUuid}",
        ],

        'TO_VALIDATE' => [

            'papier-taxi' =>
                "/papiers-taxi-a-valider/{$documentUuid}",

            'taxi_paper' =>
                "/papiers-taxi-a-valider/{$documentUuid}",

            'note-de-frais' =>
                "/notes-de-frais-a-valider/{$documentUuid}",

            'fee_note' =>
                "/notes-de-frais-a-valider/{$documentUuid}",

            'fiche-a-regulariser' =>
                "/fiches-a-regulariser-a-valider/{$documentUuid}",

            'regularization_sheet' =>
                "/fiches-a-regulariser-a-valider/{$documentUuid}",
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | URL par défaut
    |--------------------------------------------------------------------------
    */

    $path = "/details-du-document/{$documentUuid}";

    /*
    |--------------------------------------------------------------------------
    | Contexte + type connus
    |--------------------------------------------------------------------------
    */

    if (
        $context &&
        $documentTypeCode &&
        isset(
            $routes[$context][$documentTypeCode]
        )
    ) {
        $path =
            $routes[$context][$documentTypeCode];
    }

    /*
    |--------------------------------------------------------------------------
    | URL finale
    |--------------------------------------------------------------------------
    */

    return config(
        'services.frontend_service.base_url'
    ) . $path;
}

    /**
     * Résout le propriétaire du document.
     */
    public function buildOwner(
        array $documentData
    ): array {

        $userId = isset($documentData['created_by'])
            ? (int) $documentData['created_by']
            : null;

        $user = $this->resolveUser(
            $userId
        );

        return [
            'id' => $userId,

            'user' => $user,

            'name' =>
                $this->getUserName($user),

            'email' =>
                $this->getUserEmail($user),
        ];
    }

    /**
     * Résout l'acteur d'une action workflow.
     */
    public function buildActor(
        ?int $actorId
    ): array {

        $user = $this->resolveUser(
            $actorId
        );

        return [
            'id' => $actorId,

            'user' => $user,

            'name' =>
                $this->getUserName($user),

            'email' =>
                $this->getUserEmail($user),
        ];
    }

    /**
     * Construit les informations communes du document.
     */
    public function OldbuildDocument(
        string $documentUuid,
        array $documentData
    ): array {

        $beneficiary =
            isset($documentData['actor_details'])
                && is_array($documentData['actor_details'])
                ? $documentData['actor_details']
                : [];

        $documentType =
            isset($documentData['document_type'])
                && is_array($documentData['document_type'])
                ? $documentData['document_type']
                : [];

        return [

            'document_url' =>
                config(
                    'services.frontend_service.base_url'
                )
                . "/mes-papiers-taxi/{$documentUuid}",

            'document_title' =>
                isset($documentData['title'])
                    ? $documentData['title']
                    : '',

            'document_reference' =>
                isset($documentData['reference'])
                    ? $documentData['reference']
                    : '',

            'document_id' =>
                $documentUuid,

            'document_type' =>
                $documentType['name']
                ?? $documentType['code']
                ?? $documentType['slug']
                ?? '',

            'document_type_code' =>
                $documentType['code']
                ?? $documentType['slug']
                ?? '',

            'beneficiary_name' =>
                $beneficiary['name']
                ?? $beneficiary['nom']
                ?? '',

            'beneficiary_email' =>
                $beneficiary['email']
                ?? '',
        ];
    }

    /**
 * Construit les informations communes du document.
 */
public function buildDocument(
    string $documentUuid,
    array $documentData,
    ?array $config = null
): array {

    $beneficiary =
        isset($documentData['actor_details'])
            && is_array($documentData['actor_details'])
            ? $documentData['actor_details']
            : [];

    $documentType =
        isset($documentData['document_type'])
            && is_array($documentData['document_type'])
            ? $documentData['document_type']
            : [];

    $documentTypeCode = $documentType['slug']
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | Contexte de navigation
    |--------------------------------------------------------------------------
    */

    $context =
        isset($config['context'])
            ? $config['context']
            : null;

    /*
    |--------------------------------------------------------------------------
    | URL frontend
    |--------------------------------------------------------------------------
    */

    $documentUrl =
        $this->buildDocumentUrl(
            $documentUuid,
            $context,
            $documentTypeCode
        );

    return [

        'document_url' =>
            $documentUrl,

        'document_title' =>
            isset($documentData['title'])
                ? $documentData['title']
                : '',

        'document_reference' =>
            isset($documentData['reference'])
                ? $documentData['reference']
                : '',

        'document_id' =>
            $documentUuid,

        'document_type' =>
            $documentType['name']
            ?? $documentType['code']
            ?? $documentType['slug']
            ?? '',

        'document_type_code' =>
            $documentTypeCode
            ?? '',

        'beneficiary_name' =>
            $beneficiary['name']
            ?? $beneficiary['nom']
            ?? '',

        'beneficiary_email' =>
            $beneficiary['email']
            ?? '',
    ];
}

    /**
     * Formate une date d'historique.
     */
    public function formatHistoryDate(
        $history
    ): string {

        $date = null;

        if (
            $history &&
            $history->created_at
        ) {
            $date = $history->created_at;
        } else {
            $date = now();
        }

        return ucfirst(
            $date
                ->locale('fr')
                ->translatedFormat(
                    'l \l\e d F Y à H:i'
                )
        );
    }

    /**
     * Retourne le commentaire de l'historique.
     */
    public function getHistoryComment(
        $history
    ): string {

        if (!$history) {
            return '';
        }

        return trim(
            $history->comment ?? ''
        );
    }

/**
 * Retourne le sujet correspondant au type
 * de document et à l'action workflow.
 *
 * Exemples :
 *
 * returned
 * rejected
 * validated
 * approved
 * signed
 * submitted
 */
public function getSubject(
    array $documentData,
    string $action
): string {

    $documentType =
        $documentData['document_type']['code']
        ?? $documentData['document_type']['slug']
        ?? null;

    $subjects = [

        /*
        |--------------------------------------------------------------------------
        | RETURNED
        |--------------------------------------------------------------------------
        */

        'returned' => [

            'papier-taxi' =>
                'Papier taxi retourné pour modification',

            'fiche-a-regulariser' =>
                'Fiche de régularisation retournée pour modification',

            'mission' =>
                'Demande de mission retournée pour modification',

            'demande-d-absence' =>
                'Demande de congé retournée pour modification',

            'absence' =>
                'Demande d’absence retournée pour modification',

            'note-de-frais' =>
                'Note de frais retournée pour modification',

            'facture-fournisseur' =>
                'Facture fournisseur retournée pour modification',
        ],

        /*
        |--------------------------------------------------------------------------
        | REJECTED
        |--------------------------------------------------------------------------
        */

        'rejected' => [

            'papier-taxi' =>
                'Papier taxi rejeté',

            'fiche-a-regulariser' =>
                'Fiche de régularisation rejetée',

            'mission' =>
                'Demande de mission rejetée',

            'demande-d-absence' =>
                'Demande de congé rejetée',

            'absence' =>
                'Demande d’absence rejetée',

            'note-de-frais' =>
                'Note de frais rejetée',

            'facture-fournisseur' =>
                'Facture fournisseur rejetée',
        ],


                /*
        |--------------------------------------------------------------------------
        | APPROVED
        |--------------------------------------------------------------------------
        */

        'approved' => [

            'demande-d-absence' =>
                'Demande de congé approuvée',

            'absence' =>
                'Demande d’absence approuvée',
        ],
        
        /*
        |--------------------------------------------------------------------------
        | SIGNED
        |--------------------------------------------------------------------------
        */

        'signed' => [

            'papier-taxi' =>
                'Papier taxi signé pour accord de paiement',

            'fiche-a-regulariser' =>
                'Fiche de régularisation signée pour accord de paiement',

            'note-de-frais' =>
                'Note de frais signée pour accord de paiement',

        ],
    ];

    return $subjects[$action][$documentType]
        ?? $this->getDefaultSubject($action);
}

/**
 * Sujet générique lorsqu'aucun mapping
 * spécifique n'est défini.
 */
protected function getDefaultSubject(
    string $action
): string {

    $defaults = [

        'returned' =>
            'Document retourné pour modification',

        'rejected' =>
            'Document rejeté',

        'validated' =>
            'Document validé',

        'approved' =>
            'Document approuvé',

        'signed' =>
            'Document signé pour accord de paiement',

        'submitted' =>
            'Document soumis',
    ];

    return $defaults[$action]
        ?? 'Notification concernant votre document';
}
}