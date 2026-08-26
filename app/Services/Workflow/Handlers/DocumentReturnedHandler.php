<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStatusHistory;
use App\Services\User\UserServiceClient;
use Illuminate\Support\Facades\Log;

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

    Log::info('[RETURN_NOTIFICATION] ===== START EXECUTE =====', [
        'document_uuid' => $documentUuid,
        'instance_id' => $instance->id ?? null,
        'instance_class' => is_object($instance) ? get_class($instance) : null,
        'created_by' => $documentData['created_by'] ?? null,
        'document_title' => $documentData['title'] ?? null,
        'document_reference' => $documentData['reference'] ?? null,
    ]);


    /*
    |--------------------------------------------------------------------------
    | URL du document
    |--------------------------------------------------------------------------
    */

    $document_url =
        config('services.frontend_service.base_url')
        ."/mes-papiers-taxi/{$documentData["uuid"]}";

    Log::debug('[RETURN_NOTIFICATION] Document URL generated', [
        'document_url' => $document_url,
    ]);


    /*
    |--------------------------------------------------------------------------
    | Bénéficiaire
    |--------------------------------------------------------------------------
    */

    $beneficiary = isset($documentData['actor_details'])
        ? $documentData['actor_details']
        : [];

    Log::debug('[RETURN_NOTIFICATION] Beneficiary resolved', [
        'beneficiary' => $beneficiary,
    ]);


    /*
    |--------------------------------------------------------------------------
    | Créateur / OWNER
    |--------------------------------------------------------------------------
    */

    $owner = null;
    $ownerUserData = null;

    Log::debug('[RETURN_NOTIFICATION] Resolving document owner', [
        'created_by' => $documentData['created_by'] ?? null,
    ]);

    if (
        isset($documentData['created_by']) &&
        $documentData['created_by']
    ) {

        $ownerUserData = $this->userService->find(
            (int) $documentData['created_by']
        );

        Log::info('[RETURN_NOTIFICATION] Owner userService response', [
            'user_id' => (int) $documentData['created_by'],
            'found' => !empty($ownerUserData),
            'user_data' => $ownerUserData,
        ]);

        if ($ownerUserData) {

            $owner = isset($ownerUserData['employee'])
                ? $ownerUserData['employee']
                : $ownerUserData;
        }
    }

    Log::info('[RETURN_NOTIFICATION] Owner resolved', [
        'owner' => $owner,
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Owner name resolved', [
        'owner_name' => $ownerName,
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Owner email resolved', [
        'owner_email' => $ownerEmail,
    ]);


    /*
    |--------------------------------------------------------------------------
    | Récupération du dernier retour
    |--------------------------------------------------------------------------
    */

    Log::info('[RETURN_NOTIFICATION] Searching return history', [
        'instance_id' => $instance->workflowInstance->id ?? null,
        'model_type' => WorkflowInstance::class,
        'new_status' => 'RETURNED_FOR_MODIFICATION',
    ]);

    $returnHistory = WorkflowStatusHistory::query()
        ->where(
            'model_id',
            $instance->workflowInstance->id
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

    Log::info('[RETURN_NOTIFICATION] Return history resolved', [
        'found' => !is_null($returnHistory),
        'history_id' => $returnHistory->id ?? null,
        'changed_by' => $returnHistory->changed_by ?? null,
        'created_at' => $returnHistory->created_at ?? null,
        'comment' => $returnHistory->comment ?? null,
        'new_status' => $returnHistory->new_status ?? null,
    ]);


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

        Log::info('[RETURN_NOTIFICATION] Resolving returner', [
            'changed_by' => $returnHistory->changed_by,
        ]);

        $returnerUserData = $this->userService->find(
            (int) $returnHistory->changed_by
        );

        Log::info('[RETURN_NOTIFICATION] Returner userService response', [
            'user_id' => (int) $returnHistory->changed_by,
            'found' => !empty($returnerUserData),
            'user_data' => $returnerUserData,
        ]);

        if ($returnerUserData) {

            $returnedBy = isset(
                $returnerUserData['employee']
            )
                ? $returnerUserData['employee']
                : $returnerUserData;
        }

    } else {

        Log::warning(
            '[RETURN_NOTIFICATION] No valid return history/changed_by - using TEST user',
            [
                'history_found' => !is_null($returnHistory),
                'changed_by' => $returnHistory->changed_by ?? null,
                'test_user_id' => 7,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */

        $returnerUserData = $this->userService->find(7);

        Log::info('[RETURN_NOTIFICATION] TEST returner userService response', [
            'user_id' => 7,
            'found' => !empty($returnerUserData),
            'user_data' => $returnerUserData,
        ]);

        if ($returnerUserData) {

            $returnedBy = isset(
                $returnerUserData['employee']
            )
                ? $returnerUserData['employee']
                : $returnerUserData;
        }
    }


    Log::info('[RETURN_NOTIFICATION] Returner resolved', [
        'returned_by' => $returnedBy,
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Returner name resolved', [
        'returned_by_name' => $returnedByName,
        'returned_by_name_empty' => empty($returnedByName),
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Returner email resolved', [
        'returned_by_email' => $returnedByEmail,
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Return date resolved', [
        'returned_at' => $returnedAt,
        'from_history' => !is_null($returnHistory),
    ]);


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

    Log::info('[RETURN_NOTIFICATION] Return reason resolved', [
        'reason' => $reason,
        'from_history' => !is_null($returnHistory),
    ]);


    /*
    |--------------------------------------------------------------------------
    | Données de notification
    |--------------------------------------------------------------------------
    */

    $subject = $this->getReturnedSubject($documentData);

    Log::info('[RETURN_NOTIFICATION] Notification subject resolved', [
        'subject' => $subject,
    ]);


    $notificationData = [

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
            $documentData['document_type']['name'],

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
    ];


    /*
    |--------------------------------------------------------------------------
    | LOG CRITIQUE : DONNÉES FINALES
    |--------------------------------------------------------------------------
    */

    Log::info('[RETURN_NOTIFICATION] ===== FINAL NOTIFICATION DATA =====', [
        'returned_by' => $notificationData['returned_by'],
        'returned_by_email' => $notificationData['returned_by_email'],
        'returned_at' => $notificationData['returned_at'],
        'reason' => $notificationData['reason'],
        'owner_name' => $notificationData['owner_name'],
        'owner_email' => $notificationData['owner_email'],
        'document_title' => $notificationData['document_title'],
        'document_reference' => $notificationData['document_reference'],
        'document_id' => $notificationData['document_id'],
    ]);


    /*
    |--------------------------------------------------------------------------
    | Vérification explicite
    |--------------------------------------------------------------------------
    */

    if (empty($notificationData['returned_by'])) {

        Log::error(
            '[RETURN_NOTIFICATION] !!! returned_by IS EMPTY !!!',
            [
                'return_history_id' => $returnHistory->id ?? null,
                'changed_by' => $returnHistory->changed_by ?? null,
                'returner_user_data' => $returnerUserData,
                'returned_by' => $returnedBy,
                'returned_by_name' => $returnedByName,
            ]
        );

    } else {

        Log::info(
            '[RETURN_NOTIFICATION] returned_by successfully populated',
            [
                'returned_by' => $notificationData['returned_by'],
            ]
        );
    }


    Log::info('[RETURN_NOTIFICATION] ===== END EXECUTE =====');


    return [

        'data' => $notificationData,

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
    ];

    return $subjects[$documentType]
        ?? 'Document retourné pour modification';
}
}