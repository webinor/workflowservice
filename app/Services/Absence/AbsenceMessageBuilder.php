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

        // throw new \Exception("Error Processing Request", 1);
        

        $type = $absence['type'] ?? 'Absence';

        $leaveType = $absence['leave_type']['name'] ?? 'Absence';


        $reason = $absence['reason'] ?? null;

        $departureDate = $absence['departure_date'] ?? null;
        $departureTime = $absence['departure_time'] ?? null;

        $returnDate = $absence['return_date'] ?? null;
        $returnTime = $absence['return_time'] ?? null;

        $duration = $absence['duration'] ?? null;

        $dutiesHandover = $absence['duties_handover'] ?? false;
        $handoverDetails = $absence['handover_details'] ?? null;

        $viewRoute = ltrim($doc['document_type']['view_route'], '/');

        $message = sprintf(
            "%s\n\n".
            "Vous avez une nouvelle demande de $type à traiter.\n\n".
            "👤 Collaborateur : %s\n".
            "📌 Type : %s\n",
            $this->greeting(),
            $beneficiary,
            $type,//$leaveType
        );

        // Le motif n'est affiché que lorsqu'il existe
        if (!empty($reason)) {
            $message .= "📝 Motif : {$reason}\n";
        }

        $message .= sprintf(
            "📅 Départ : %s %s\n".
            "📅 Retour : %s %s",
            $departureDate
                ? date('d/m/Y', strtotime($departureDate))
                : '-',
            $departureTime ?? '',
            $returnDate
                ? date('d/m/Y', strtotime($returnDate))
                : '-',
            $returnTime ?? ''
        );

        if (!empty($duration)) {
            $message .= "\n⏳ Durée : {$duration} jour(s)";
        }

        // $message .= "\n🔄 Passation de service : " . ($dutiesHandover ? "Oui" : "Non");

        if ($dutiesHandover && !empty($handoverDetails)) {
            // $message .= "\n📋 Détails : {$handoverDetails}";
        }

        return [
            "title" => "📅 Nouvelle demande de {$type} à valider",
            "bgColor" => "#3b82f6",
            "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour consulter la demande.",
            "actionButtonText" => "Voir la demande",
            "message" => $message,
            "view_route" => $viewRoute,
        ];
    }
}