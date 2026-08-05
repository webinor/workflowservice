<?php

namespace App\Services\User;

use App\Services\HttpClientService;

class UserEnricher
{
    public function enrich(array $items, string $field = 'user_id'): array
    {
        $userIds = collect($items)
            ->pluck($field)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($userIds)) {
            return $items;
        }



        $client = HttpClientService::service('user');

        $users = $client->get(
            'getByIds',
            ['ids' => implode(',', $userIds)]
        )['data'] ?? [];

//   throw new \Exception(json_encode(collect($users)), 1);
//   throw new \Exception(json_encode(collect($users)->pluck('responsibilities.code')), 1);



        $usersById = collect($users)
            ->keyBy('id')
            ->toArray();

        return collect($items)
            ->map(function ($item) use ($usersById, $field) {

                $item['user'] =  $usersById[$item[$field]] ?? null;

                return $item;
            })
            ->values()
            ->toArray();
    }
}