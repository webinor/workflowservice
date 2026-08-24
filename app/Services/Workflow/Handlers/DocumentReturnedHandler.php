<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStatusHistory;
use App\Services\User\UserServiceClient;

class DocumentReturnedHandler
    implements WorkflowEventHandlerInterface
{
    protected UserServiceClient $userService;

    public function __construct(
        UserServiceClient $userService
    ) {
        $this->userService = $userService;
    }

    public function execute(
        $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array {

        $document_url = config('services.frontend_service.base_url')."/mes-papiers-taxi/{$documentData["uuid"]}" ;

        /*
        |--------------------------------------------------------------------------
        | Bénéficiaire
        |--------------------------------------------------------------------------
        */

        $beneficiary = isset($documentData['actor_details'])
            ? $documentData['actor_details']
            : [];


        /*
        |--------------------------------------------------------------------------
        | Créateur / OWNER du document
        |--------------------------------------------------------------------------
        |
        | created_by contient le user_id de la personne qui a créé/soumis
        | le document.
        |
        | UserService retourne également les informations de l'employé
        | associé à cet utilisateur.
        |--------------------------------------------------------------------------
        */

        $owner = null;
        $ownerUserData = null;

        if (
            isset($documentData['created_by']) &&
            $documentData['created_by']
        ) {

            $ownerUserData = $this->userService->find(
                (int) $documentData['created_by']
            );

            if ($ownerUserData) {

                $owner = isset($ownerUserData['employee'])
                    ? $ownerUserData['employee']
                    : $ownerUserData;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Nom du créateur
        |--------------------------------------------------------------------------
        */

        $ownerName = '';

        if ($owner) {

            $ownerName =
                isset($owner['nom'])
                    ? $owner['nom']
                    : (
                        isset($owner['name'])
                            ? $owner['name']
                            : ''
                    );

            if (
                isset($owner['prenom']) &&
                !empty($owner['prenom'])
            ) {
                $ownerName .=
                    ' ' . $owner['prenom'];
            }

            $ownerName = trim($ownerName);
        }


        /*
        |--------------------------------------------------------------------------
        | Email du créateur
        |--------------------------------------------------------------------------
        */

        $ownerEmail = '';

        if ($owner) {

            $ownerEmail =
                isset($owner['email'])
                    ? $owner['email']
                    : '';

        }


        /*
        |--------------------------------------------------------------------------
        | Récupération du dernier retour
        |--------------------------------------------------------------------------
        */

        $returnHistory = WorkflowStatusHistory::query()
            ->where(
                'model_id',
                $instance->id
            )
            ->where(
                'model_type',
                WorkflowInstance::class
            )
            ->where(
                'new_status',
                'RETURNED_FOR_MODIFICATION'
            )
            ->latest('id')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Informations de la personne ayant effectué le retour
        |--------------------------------------------------------------------------
        */

        $returnedBy = null;
        $returnerUserData = null;

        if (
            $returnHistory &&
            $returnHistory->changed_by
        ) {

            $returnerUserData = $this->userService->find(
                (int) $returnHistory->changed_by
            );

            if ($returnerUserData) {

                $returnedBy = isset(
                    $returnerUserData['employee']
                )
                    ? $returnerUserData['employee']
                    : $returnerUserData;
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | TEST
            |--------------------------------------------------------------------------
            |
            | Conservé volontairement pour les tests sans commit.
            |--------------------------------------------------------------------------
            */

            $returnerUserData = $this->userService->find(
                7
            );

            if ($returnerUserData) {

                $returnedBy = isset(
                    $returnerUserData['employee']
                )
                    ? $returnerUserData['employee']
                    : $returnerUserData;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Nom de la personne ayant effectué le retour
        |--------------------------------------------------------------------------
        */

        $returnedByName = '';

        if ($returnedBy) {

            $returnedByName =
                isset($returnedBy['nom'])
                    ? $returnedBy['nom']
                    : (
                        isset($returnedBy['name'])
                            ? $returnedBy['name']
                            : ''
                    );

            if (
                isset($returnedBy['prenom']) &&
                !empty($returnedBy['prenom'])
            ) {
                $returnedByName .=
                    ' ' . $returnedBy['prenom'];
            }

            $returnedByName = trim($returnedByName);
        }


        /*
        |--------------------------------------------------------------------------
        | Email de la personne ayant effectué le retour
        |--------------------------------------------------------------------------
        */

        $returnedByEmail = '';

        if ($returnedBy) {

            $returnedByEmail =
                isset($returnedBy['email'])
                    ? $returnedBy['email']
                    : '';
        }


        /*
        |--------------------------------------------------------------------------
        | Date du retour
        |--------------------------------------------------------------------------
        */

        if (
            $returnHistory &&
            $returnHistory->created_at
        ) {

            $returnedAt = $returnHistory
                ->created_at
                ->locale('fr')
                ->translatedFormat(
                    'l \l\e d F Y à H:i'
                );

        } else {

            $returnedAt = now()
                ->locale('fr')
                ->translatedFormat(
                    'l \l\e d F Y à H:i'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Motif
        |--------------------------------------------------------------------------
        */

        $reason = '';

        if ($returnHistory) {

            $reason = $returnHistory->comment ?? '';

        } else {

            /*
            |--------------------------------------------------------------------------
            | TEST
            |--------------------------------------------------------------------------
            */

            $reason = 'Montant élevé';
        }


        /*
        |--------------------------------------------------------------------------
        | Données de notification
        |--------------------------------------------------------------------------
        */

        $subject = $this->getReturnedSubject($documentData);

        return [

            'data' => [

                'document_url' => $document_url,

                'subject' => $subject,

                /*
                |--------------------------------------------------------------------------
                | OWNER
                |--------------------------------------------------------------------------
                */

                'owner_name' =>
                    $ownerName,

                'owner_email' =>
                    $ownerEmail,

                /*
                |--------------------------------------------------------------------------
                | Document
                |--------------------------------------------------------------------------
                */

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
                    'PAPIER_TAXI',

                /*
                |--------------------------------------------------------------------------
                | Bénéficiaire
                |--------------------------------------------------------------------------
                */

                'beneficiary_name' =>
                    isset($beneficiary['name'])
                        ? $beneficiary['name']
                        : (
                            isset($beneficiary['nom'])
                                ? $beneficiary['nom']
                                : ''
                        ),

                'beneficiary_email' =>
                    isset($beneficiary['email'])
                        ? $beneficiary['email']
                        : '',

                /*
                |--------------------------------------------------------------------------
                | Retour
                |--------------------------------------------------------------------------
                */

                'returned_by' =>
                    trim($returnedByName),

                'returned_by_email' =>
                    $returnedByEmail,

                'returned_at' =>
                    ucfirst($returnedAt),

                'reason' =>
                    $reason,

                'status' =>
                    'RETURNED_FOR_MODIFICATION',

                'message' =>
                    'Votre document a été retourné pour modification.',
            ],

            'attachments' => [],
        ];
    }

    protected function getReturnedSubject(array $documentData): string
{
    $documentType = $documentData['document_type']['code']
        ?? $documentData['document_type']['slug']
        ?? null;

    $subjects = [

        'papier-taxi' =>
            'Papier taxi retourné pour modification',

        'fiche-regularisation' =>
            'Fiche de régularisation retournée pour modification',

        'ordre-mission' =>
            'Ordre de mission retourné pour modification',

        'demande-conge' =>
            'Demande de congé retournée pour modification',

        'absence' =>
            'Demande d’absence retournée pour modification',

        'note-frais' =>
            'Note de frais retournée pour modification',

        'facture-fournisseur' =>
            'Facture fournisseur retournée pour modification',
    ];

    return $subjects[$documentType]
        ?? 'Document retourné pour modification';
}
}