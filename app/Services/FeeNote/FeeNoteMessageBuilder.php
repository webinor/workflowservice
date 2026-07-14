<?php

namespace App\Services\FeeNote;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class FeeNoteMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $feeNote = $doc['fee_note'] ?? [];

        $reason = $feeNote['reason'] ?? 'Sans motif';

        $amount = $feeNote['amount'] ?? 0;

        $view_route = ltrim($doc["document_type"]["view_route"], '/');

        return [

        "title" => "🧾 Nouvelle note de frais à valider",
        "bgColor" => "#7a2cee",
        
        "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour accéder à la note de frais",
        "actionButtonText" => "Voir la note de frais",
            
        "message" => sprintf(
            "%s\n\n".
            "Vous avez une nouvelle note de frais à traiter.\n\n".
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