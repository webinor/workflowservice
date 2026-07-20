<?php

namespace App\Services;

use DateTime;

abstract class AbstractWorkflowNotificationMessageBuilder
{
    protected function greeting(): string
    {
        $hour = (int) date('H');

        if ($hour < 12) {
            return "🌅 Bonjour,";
        }

        if ($hour < 18) {
            return "☀️ Bon après-midi,";
        }

        return "🌙 Bonsoir,";
    }

    /**
     * Construit les informations de période d'une mission.
     *
     * @param array $mission
     * @param bool $planned true = Planned, false = Actual
     * @return array{
     *     departure:string,
     *     arrivalSite:string,
     *     departureSite:string,
     *     return:string,
     *     duration:string
     * }
     */
protected function buildMissionPeriod(array $mission, bool $planned = true): array
{
    $suffix = $planned ? 'planned' : 'actual';

    $departure = $this->buildDateTime(
        $mission["departure_date_base_{$suffix}"] ?? null,
        $mission["departure_time_base_{$suffix}"] ?? null
    );

    $arrivalSite = $this->buildDateTime(
        $mission["arrival_date_site_{$suffix}"] ?? null,
        $mission["arrival_time_site_{$suffix}"] ?? null
    );

    $departureSite = $this->buildDateTime(
        $mission["departure_date_site_{$suffix}"] ?? null,
        $mission["departure_time_site_{$suffix}"] ?? null
    );

    $return = $this->buildDateTime(
        $mission["arrival_date_base_{$suffix}"] ?? null,
        $mission["arrival_time_base_{$suffix}"] ?? null
    );

    return [
        // 'departure'     => $departure ? $departure->format('d/m/Y à H:i') : '-',
        'departure'     => $departure ? $departure->format('d/m/Y') : '-',
        'arrivalSite'   => $arrivalSite ? $arrivalSite->format('d/m/Y') : '-',
        'departureSite' => $departureSite ? $departureSite->format('d/m/Y') : '-',
        'return'        => $return ? $return->format('d/m/Y') : '-',
        'duration'      => $this->formatDuration($departure, $return),
    ];
}

protected function buildDateTime($date, $time)
{
    // if (empty($date) || empty($time)) {
    if (empty($date)) {
        return null;
    }

    // return new DateTime($date . ' ' . $time);
    return new DateTime($date);
}

protected function providerTypeLabel(?string $type): string
{
    $labels = [
        'IT_PROVIDER'      => 'Prestataire informatique',
        'MEDICAL_PROVIDER' => 'Prestataire médical',
        'IT_SUPPLIER'      => 'Fournisseur informatique',
        'MEDICAL_SUPPLIER' => 'Fournisseur médical',
    ];

    return $labels[$type] ?? 'Non défini';
}

protected function formatDuration($start, $end)
{


    if (!$start || !$end) {
        return '-';
    }

    if ($end < $start) {
        return '-';
    }

    $interval = $start->diff($end);




    $days = $interval->days;

    if ($days === 0) {
        return "1 jour";
    }

    // throw new \Exception(json_encode($days . ' jour' . ($days > 1 ? 's' : '')), 1);

    return $days . ' jour' . ($days > 1 ? 's' : '');
}

protected function oldformatDuration($start, $end)
{
    if (!$start || !$end) {
        return '-';
    }

    $interval = $start->diff($end);

    $parts = [];

    if ($interval->y > 0) {
        $parts[] = $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    }

    if ($interval->m > 0) {
        $parts[] = $interval->m . ' mois';
    }

    if ($interval->d > 0) {
        $parts[] = $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    }

    if ($interval->h > 0) {
        $parts[] = $interval->h . ' h';
    }

    if ($interval->i > 0) {
        $parts[] = $interval->i . ' min';
    }

    return empty($parts)
        ? "Moins d'une minute"
        : implode(' ', $parts);
}
}