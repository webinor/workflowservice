<?php

namespace App\Services\InvoiceProvider;


class InvoiceProviderEnricher
{
    public function enrich(array $doc, ?array $ctx): array
    {
        $workflowCompleted = 
            ($ctx['workflow_status'] ?? null) === 'COMPLETE';


        $invoice = $doc['invoice_provider'] ?? [];

        $providerType = $invoice['provider_type'] ?? null;


        $doc['actions'] = [

            [
                "code" => "DOWNLOAD",

                "enabled" => $workflowCompleted,

                "reason" => $workflowCompleted
                    ? null
                    : "Facture non encore validée",
            ],


            // Exemple si tu ajoutes une validation spécifique
            [
                "code" => "VIEW",

                "enabled" => true,
            ],
        ];


        $doc['availability'] = [

            "can_download" => $workflowCompleted,

            "can_view" => true,

        ];


        return $doc;
    }
}