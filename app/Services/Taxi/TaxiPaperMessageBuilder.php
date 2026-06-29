<?php

namespace App\Services\Taxi;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class TaxiPaperMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder 
{
    public function build(array $doc): array
    {
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $paper = $doc['taxi_paper'] ?? [];

        $reason = $paper['reason'] ?? '';

        $rides = $paper['rides'] ?? [];

        $count = count($rides);

        $total = 0;

        foreach ($rides as $ride) {
            $total += $ride['montant'] ?? 0;
        }

         $firstRide = $rides[0]['trajet'] ?? null;

        $message = sprintf(
            "%s\n\n".
            "Vous avez un nouveau papier taxi à traiter.\n\n".
            "👤 Collaborateur : %s\n".
            "📝 Motif : %s\n".
            "🚕 Trajets : %d\n".
            "💰 Montant : %s FCFA",
            $this->greeting(),
            $beneficiary,
            $reason,
            $count,
            number_format($total, 0, ',', ' ')
        );

if ($firstRide && sizeof($rides)>1) {
    $message .= "\nPremier trajet : ".$firstRide;
}
        return 

        [

        "title" => "🚕 Nouveau papier taxi à valider",
        "bgColor" => "#f79009",
        
        "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour accéder au papier taxi",
        "actionButtonText" => "Voir le papier taxi",
            
        "message" => $message];
    }
}