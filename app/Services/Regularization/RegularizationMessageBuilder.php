<?php

namespace App\Services\Regularization;


use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class RegularizationMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $regularization_sheet = $doc['regularization_sheet'] ?? [];

        $reason = $regularization_sheet['reason'] ?? 'Sans motif';

        $amount = $regularization_sheet['amount'] ?? 0;

        $view_route = ltrim($doc["document_type"]["view_route"], '/');

        return [

        "title" => "🧾 Nouvelle fiche à regulariser à valider",
        "bgColor" => "#2b3b62",
        
        "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour accéder à la fiche à regulariser",
        "actionButtonText" => "Voir la fiche à regulariser",
            
        "message" => sprintf(
            "%s\n\n".
            "Vous avez une nouvelle fiche à regulariser à traiter.\n\n".
            "👤 Collaborateur : %s\n".
            "📝 Motif : %s\n".
            "💰 Montant : %s FCFA",
            $this->greeting(),
            $beneficiary,
            $reason,
            number_format($amount, 0, ',', ' '),
        ),

         "view_route"=>$view_route
        
        ];
    }
}