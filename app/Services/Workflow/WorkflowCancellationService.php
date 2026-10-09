<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowStatusHistory;
use App\Services\User\UserServiceClient;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WorkflowCancellationService
{
    protected UserServiceClient $userServiceClient;

    public function __construct(
        UserServiceClient $userServiceClient
    ) {
        $this->userServiceClient = $userServiceClient;
    }

    /**
     * Annule une opération et la transaction financière associée.
     *
     * Le workflow n'est annulé qu'après confirmation
     * de l'annulation de la transaction par le UserService.
     *
     * @param WorkflowInstance $instance
     * @param int $userId
     * @param string $reason
     * @param string $documentUuid
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function cancel(
        WorkflowInstance $instance,
        int $userId,
        string $reason,
        string $documentUuid
    ): array {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(
                'Annulation impossible : le motif est obligatoire.'
            );
        }

        // if ($documentUuid <= 0) {
        //     throw new RuntimeException(
        //         'Annulation impossible : la transaction financière '
        //         . 'associée est introuvable.'
        //     );
        // }

        if ($instance->status === 'CANCELLED') {
            throw new RuntimeException(
                'Cette opération a déjà été annulée. '
                . 'Aucune modification supplémentaire n’a été effectuée.'
            );
        }

            //   $financialResult =
            //     $this->userServiceClient->cancelTransactions(
            //         $documentUuid,
            //         $userId,
            //         $reason
            //     );

            //     throw new Exception(json_encode($financialResult), 1);


        /*
         * 1. Annuler la transaction dans le UserService.
         */
        try {
            $financialResult =
                $this->userServiceClient->cancelTransactions(
                    $documentUuid,
                    $userId,
                    $reason
                );

                // throw new Exception(json_encode($financialResult), 1);
                
        } catch (Throwable $e) {
            Log::error(
                'Échec de l’annulation de la transaction financière.',
                [
                    'workflow_instance_id' => $instance->id,
                    'documentUuid' => $documentUuid,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'L’opération n’a pas été annulée : le service financier '
                . 'n’a pas pu confirmer l’annulation de la transaction. '
                . 'Veuillez réessayer ou contacter l’administrateur.',
                0,
                $e
            );
        }

        if (
            !is_array($financialResult)
            || !(
                ($financialResult['success'] ?? false)
                || ($financialResult['data']['success'] ?? false)
            )
        ) {
            throw new RuntimeException(
                'L’opération n’a pas été annulée : '
                . 'l’annulation des transactions financières '
                . 'n’a pas été confirmée.'
            );
        }

        /*
         * 2. Mettre à jour le workflow et son historique.
         */
        try {
            $oldStatus = $instance->status;

            DB::transaction(function () use (
                $instance,
                $userId,
                $reason,
                $oldStatus
            ) {
                $instance->update([
                    'status' => 'CANCELLED',
                ]);

                $instance->instance_steps()
                    ->whereIn('status', [
                        'PENDING',
                        'NOT_STARTED',
                    ])
                    ->update([
                        'status' => 'CANCELLED',
                    ]);

                WorkflowStatusHistory::create([
                    'model_id' => $instance->id,
                    'model_type' => WorkflowInstance::class,
                    'old_status' => $oldStatus,
                    'new_status' => 'CANCELLED',
                    'changed_by' => $userId,
                    'comment' => $reason,
                ]);
            });
        } catch (Throwable $e) {
            Log::critical(
                'Les transactions financières ont été annulées, '
                . 'mais la mise à jour du workflow a échoué.',
                [
                    'workflow_instance_id' => $instance->id,
                    'documentUuid' => $documentUuid,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Les transactions financières ont été annulées, '
                . 'mais le workflow n’a pas pu être mis à jour. '
                . 'Ne relancez pas une nouvelle annulation sans vérifier '
                . 'l’état de la transaction. Une intervention technique '
                . 'peut être nécessaire.',
                0,
                $e
            );
        }

        return [
            'success' => true,
            //  "message" => "Document annulé avec succès",
            'message' => 'Opération annulée avec succès. '
                . 'Les transactions financières associées ont également été '
                . 'annulées et le motif a été enregistré.',
            'workflow_instance_id' => $instance->id,
            'old_status' => $oldStatus,
            'new_status' => 'CANCELLED',
            'transaction' => $financialResult['data']
                ?? $financialResult,
        ];
    }
}