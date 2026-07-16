<?php

namespace App\Services\Purchase;


use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class PurchaseRequestMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $purchase = $doc['purchase_request'] ?? [];

        $title = $purchase['title'] ?? 'Demande d\'achat';
        $amount = $doc['amount'] ?? null;
        $reference = $doc['reference'] ?? null;

        $viewRoute = ltrim($doc['document_type']['view_route'], '/');

        // throw new \Exception($viewRoute, 1);
        

        $message = sprintf(
            "%s\n\n".
            "Vous avez une nouvelle demande d'achat à traiter.\n\n".
            "👤 Demandeur : %s\n".
            "📝 Objet : %s\n",
            $this->greeting(),
            $beneficiary,
            $title
        );

        if (!empty($reference)) {
            $message .= "📄 Référence : {$reference}\n";
        }

        if (!empty($amount)) {
            $message .= "💰 Montant : " . number_format($amount, 0, ',', ' ') . " FCFA\n";
        }

        return [
            "title" => "🛒 Nouvelle demande d'achat à valider",
            "bgColor" => "#10b981",
            "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour consulter la demande d'achat.",
            "actionButtonText" => "Voir la demande",
            "message" => $message,
            "view_route" => $viewRoute,
        ];
    }
}