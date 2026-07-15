<?php

namespace App\Services\Mission;

use App\Contracts\WorkflowNotificationMessageBuilder;
use App\Services\AbstractWorkflowNotificationMessageBuilder;

class MissionMessageBuilder extends AbstractWorkflowNotificationMessageBuilder implements WorkflowNotificationMessageBuilder
{
    public function build(array $doc): array
    {
        
        $beneficiary = $doc['actor_details']['nom'] ?? 'Collaborateur';

        $mission = $doc['mission'] ?? [];

        $expenses = $mission['mission_expenses'] ?? [];

$totalPlanned = array_sum(array_map(function ($e) {
    return $e['planned_total'] ?? 0;
}, $expenses));

$totalReal = array_sum(array_map(function ($e) {
    return $e['amount'] ?? 0;
}, $expenses));

        $period = $this->buildMissionPeriod($mission);

$departureDate = $period['departure'];
$arrivalSite   = $period['arrivalSite'];
$departureSite = $period['departureSite'];
$returnDate    = $period['return'];
$duration      = $period['duration'];
$view_route = ltrim($doc["document_type"]["view_route"], '/');

        $destination = $mission['destination'] ?? 'Non renseignée';
        $reason = $mission['description'] ?? ($doc['title'] ?? 'Non renseigné');
        // $departureDate = $mission['departure_date'] ?? ($mission['date_depart'] ?? '-');
        // $returnDate = $mission['return_date'] ?? ($mission['date_retour'] ?? '-');
        $amount =  $totalPlanned;//$mission['estimated_amount'] ?? ($mission['montant'] ?? 0);

        $message = sprintf(
    "%s\n\n".
    "Vous avez une nouvelle mission à traiter.\n\n".
    "👤 Collaborateur : %s\n".
    "📍 Destination : %s\n".
    "🎯 Objet : %s\n".
    "🚌 Départ base : %s\n".
    // "📍 Arrivée site : %s\n".
    // "🚗 Départ site : %s\n".
    "🏢 Retour base : %s\n".
    "⏳ Durée prévue : %s",
    $this->greeting(),
    $beneficiary,
    $destination,
    $reason,
    $departureDate,
    // $arrivalSite,
    // $departureSite,
    $returnDate,
    $duration
);

        if ($duration) {
            // $message .= "\n⏳ Durée : {$duration}";
        }

        if ($amount > 0) {
            $message .= "\n💰 Budget estimé : " . number_format($amount, 0, ',', ' ') . " FCFA";
        }

        return [
            "title" => "✈️ Nouvelle mission à valider",
            "bgColor" => "#2563eb",
            "actionText" => "🚀 Cliquez sur le bouton ci-dessous pour consulter la demande de mission.",
            "actionButtonText" => "Voir la mission",
            "message" => $message,
            "view_route"=>$view_route
        ];
    }
}