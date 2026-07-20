<?php

namespace App\Services\InvoiceProvider;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class InvoiceProviderMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        // $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        
        
        $invoice = $doc['invoice_provider'] ?? [];

        // throw new \Exception(json_encode($invoice['amount']), 1);

        $providerType = $invoice['provider_type'] ?? 'Non défini';

        $provider = $invoice['provider'] ?? 'Prestataire inconnu';

        $providerReference = $invoice['provider_reference'] ?? 'Non renseignée';

        $depositDate = $invoice['deposit_date'] ?? null;

        $amount = $invoice['amount'] ?? 0;

        // throw new \Exception(json_encode($amount), 1);


        $view_route = ltrim($doc["document_type"]["view_route"], '/');

        return [

            "title" => "🧾 Nouvelle facture fournisseur à valider",

            "bgColor" => "#2563eb",

            "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour accéder à la facture fournisseur",

            "actionButtonText" => "Voir la facture",

            "message" => sprintf(
                "%s\n\n".
                "Vous avez une nouvelle facture fournisseur à traiter.\n\n".
                // "👤 Collaborateur : %s\n".
                "🏢 Type : %s\n".
                "🏪 Prestataire : %s\n".
                "🔖 Référence fournisseur : %s\n".
                "📅 Date de dépôt : %s\n".
                "💰 Montant : %s FCFA",

                $this->greeting(),
                // $beneficiary,
                $this->providerTypeLabel($providerType),
                $provider,
                $providerReference,
                $depositDate ? date('d/m/Y', strtotime($depositDate)) : 'Non renseignée',
                number_format($amount, 0, ',', ' ')
            ),

            "view_route" => $view_route
        ];
    }
}