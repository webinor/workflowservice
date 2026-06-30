<?php

namespace App\Services\Actor;


use App\Services\HttpClientService;

class ActorEnricher
{
    public function enrich(array $items, string $field = 'actor_id'): array
    {
        $actorIds = collect($items)
            ->pluck($field)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($actorIds)) {
            return $items;
        }

        // throw new \Exception(json_encode($actorIds), 1);
        

        $client = HttpClientService::service('employee');

        $actors = $client->get(
            'employees-by-ids',
            ['ids' => implode(',', $actorIds)]
        )['data']['data'] ?? [];

        // throw new \Exception(json_encode($actors), 1);


        $actorsById = collect($actors)
            ->keyBy('id')
            ->toArray();

        // throw new \Exception(json_encode($actorsById), 1);


        return collect($items)
            ->map(function ($item) use ($actorsById, $field) {

                $item['actor'] =  $actorsById[$item[$field]] ?? null;

                return $item;
            })
            ->values()
            ->toArray();
    }
}