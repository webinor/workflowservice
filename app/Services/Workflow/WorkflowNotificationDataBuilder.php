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
    public function buildDocument(
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
     * Cette méthode est volontairement générique :
     *
     * returned
     * rejected
     * validated
     * approved
     * etc.
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
                'Document signé',

            'submitted' =>
                'Document soumis',
        ];

        return $defaults[$action]
            ?? 'Notification concernant votre document';
    }
}