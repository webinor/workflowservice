<?php

namespace App\Services\Absence;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class AbsenceMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $absence = $doc['absence_request'] ?? [];

        $reason = $absence['reason'] ?? 'Non renseigné';

        $departureDate = $absence['departure_date'] ?? null;
        $departureTime = $absence['departure_time'] ?? null;

        $returnDate = $absence['return_date'] ?? null;
        $returnTime = $absence['return_time'] ?? null;

        $dutiesHandover = $absence['duties_handover'] ?? false;

        $handoverDetails = $absence['handover_details'] ?? null;

        $message = sprintf(
            "%s\n\n".
            "Vous avez une nouvelle demande d'absence à traiter.\n\n".
            "👤 Collaborateur : %s\n".
            "📝 Motif : %s\n".
            "📅 Départ : %s %s\n".
            "📅 Retour : %s %s",
            $this->greeting(),
            $beneficiary,
            $reason,
            $departureDate
                ? date('d/m/Y', strtotime($departureDate))
                : '-',
            $departureTime ?? '',
            $returnDate
                ? date('d/m/Y', strtotime($returnDate))
                : '-',
            $returnTime ?? ''
        );

        if ($dutiesHandover) {
            $message .= "\n🔄 Passation de service : Oui";

            if (!empty($handoverDetails)) {
                $message .= "\n📋 Détails : " . $handoverDetails;
            }
        } else {
            $message .= "\n🔄 Passation de service : Non";
        }

        return [
            "title" => "📅 Nouvelle demande d'absence à valider",
            "bgColor" => "#3b82f6",
            "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour consulter la demande d'absence",
            "actionButtonText" => "Voir la demande",
            "message" => $message,
        ];
    }
}