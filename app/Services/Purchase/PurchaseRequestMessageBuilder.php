<?php

namespace App\Services\Purchase;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;
use App\Services\Department\DepartmentServiceClient;
use App\Services\Client\ClientServiceClient;

class PurchaseRequestMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    protected DepartmentServiceClient $departmentService;
    protected ClientServiceClient $clientService;

    public function __construct(
        DepartmentServiceClient $departmentService,
        ClientServiceClient $clientService
    ) {
        $this->departmentService = $departmentService;
        $this->clientService = $clientService;
    }

    /**
     * Construire le message de notification.
     */
    public function build(array $doc): array
    {
        $actor = $this->resolveActor($doc);

        $purchase = $doc['purchase_request'] ?? [];

        $title = $purchase['title']
            ?? $doc['title']
            ?? 'Demande d\'achat';

        $amount = $doc['amount'] ?? null;
        $reference = $doc['reference'] ?? null;

        $viewRoute = ltrim(
            $doc['document_type']['view_route'] ?? '',
            '/'
        );

        $message = sprintf(
            "%s\n\n" .
            "Vous avez une nouvelle demande d'achat à traiter.\n\n" .
            "%s : %s\n" .
            "📝 Objet : %s\n",
            $this->greeting(),
            $actor['label'],
            $actor['name'],
            $title
        );

        // Récupérer les articles correspondant à l'étape actuelle.
        $items = $this->resolvePurchaseItems($purchase);

        if (!empty($items)) {
            $message .= "\n📦 Détail des articles :\n";

            foreach ($items as $index => $item) {
                $designation = $item['designation']
                    ?? $item['label']
                    ?? 'Article sans désignation';

                $quantity = $item['requested_quantity']
                    ?? $item['quantity']
                    ?? 0;

                $itemAmount = $item['requested_amount']
                    ?? $item['amount']
                    ?? 0;

                $specification = $item['specification'] ?? null;

                $message .= sprintf(
                    "\n%d. %s\n",
                    $index + 1,
                    $designation
                );

                $message .= sprintf(
                    "   • Quantité : %s\n",
                    $quantity
                );

                $message .= sprintf(
                    "   • Montant : %s FCFA\n",
                    number_format((float) $itemAmount, 0, ',', ' ')
                );

                if (!empty($specification)) {
                    $message .= sprintf(
                        "   • Spécification : %s\n",
                        $specification
                    );
                }
            }
        }

        if (!empty($reference)) {
            $message .= "\n📄 Référence : {$reference}\n";
        }

        if (!empty($amount)) {
            $message .= "💰 Montant total : " .
                number_format((float) $amount, 0, ',', ' ') .
                " FCFA\n";
        }

        return [
            'title' => "🛒 Nouvelle demande d'achat à valider",
            'bgColor' => '#10b981',
            'actionText' => '🚀 Cliquez sur le bouton ci-dessous pour consulter la demande d\'achat.',
            'actionButtonText' => 'Voir la demande',
            'message' => $message,
            'view_route' => $viewRoute,
        ];
    }

    /**
     * Déterminer les articles à afficher dans la notification.
     *
     * Si un bon de commande existe, ses articles sont prioritaires.
     * Sinon, utiliser les articles de la demande initiale.
     */
    private function resolvePurchaseItems(array $purchase): array
    {
        $requestOrder = $purchase['request_order'] ?? null;

        if (!empty($requestOrder)) {
            return $requestOrder['items']
                ?? $requestOrder['request_order_items']
                ?? [];
        }

        return $purchase['purchase_request_items'] ?? [];
    }

    /**
     * Récupérer les informations de l'acteur
     * en fonction de son type.
     */
    private function resolveActor(array $doc): array
    {
        $actorType = strtoupper($doc['actor_type'] ?? 'EMPLOYEE');
        $actorId = (int) ($doc['actor_id'] ?? 0);

        switch ($actorType) {
            case 'DEPARTMENT':

                $department = $this->departmentService->getDepartment(
                    $actorId
                );

                return [
                    'label' => 'Service demandeur',
                    'name' => $this->extractName(
                        $department,
                        'Département #' . $actorId
                    ),
                ];

            case 'CLIENT':

                $client = $this->clientService->getClient(
                    $actorId
                );

                return [
                    'label' => 'Client',
                    'name' => $this->extractName(
                        $client,
                        'Client #' . $actorId
                    ),
                ];

            case 'EMPLOYEE':
            default:

                $employee = $doc['actor_details'] ?? [];

                return [
                    'label' => 'Demandeur',
                    'name' => $this->extractName(
                        $employee,
                        'Collaborateur'
                    ),
                ];
        }
    }

    /**
     * Normaliser le nom provenant des différents services.
     */
    private function extractName(?array $data, string $fallback): string
    {
        if (empty($data)) {
            return $fallback;
        }

        return $data['name']
            ?? $data['nom']
            ?? $data['full_name']
            ?? $data['label']
            ?? $fallback;
    }
}