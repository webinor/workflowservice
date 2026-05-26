<?php

namespace App\Workflow\Handlers;

use App\Services\Notification\NotificationService;

class NotifyFinanceHandler
{
    protected NotificationService $notificationService;

    public function __construct(
        NotificationService $notificationService
    ) {
        $this->notificationService = $notificationService;
    }

    public function execute(
        int $documentId,
        $instance,
        array $config = []
    ) {

        $roles = $config['roles'] ?? [];

        return $this->notificationService
            ->notifyRoles(
                $roles,
                [
                    'document_id' => $documentId,
                    'workflow_instance_id' => $instance->id
                ]
            );
    }
}