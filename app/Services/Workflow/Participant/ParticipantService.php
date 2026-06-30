<?php

namespace App\Services\Workflow\Participant;

use App\Models\WorkflowInstance;
use App\Services\HttpClientService;
use App\Services\User\UserEnricher;
use App\Services\Workflow\Signature\BusinessSignatureResolverFactory;

class ParticipantService
{
   public function getParticipants(
    int $documentId,
    string $documentType
): array
{
    $instance = WorkflowInstance::with([
        'instance_steps.assignments'
    ])
    ->where('document_id', $documentId)
    ->firstOrFail();

    $participantResolver = app(
        ParticipantResolverFactory::class
    )->make($documentType);

    $signatureResolver =
    BusinessSignatureResolverFactory::make(
        $documentType
    );

  $businessSignatures = $signatureResolver->resolve( $instance->document_id);


     $participants =  $participantResolver->resolve($instance);


     
     $participants = app(UserEnricher::class)
    ->enrich($participants);

    
$businessSignatures = app(UserEnricher::class)
    ->enrich($businessSignatures , "actor_id");

    return ["participants"=>$participants,
    "business_signatures"=>$businessSignatures];


     $userIds = collect($participants)
    ->pluck('user_id')
    ->filter()
    ->unique()
    ->values()->toArray();


    $client = HttpClientService::service('user');

    $users = $client->get("getByIds", ["ids" => implode(",", $userIds)])['data'];

    $usersById = collect($users)
    ->keyBy('id');

    $enrichedParticipants = collect($participants)
    ->map(function ($p) use ($usersById) {

        $user = $usersById->get($p['user_id']);

        return  array_merge($p , [
                'user' => $user ? [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'signatureUrl' => $user['signatureUrl'],
                
            ] : []
        ])  ;
    })
    ->values()
    ->toArray();

    $businessSignatures = collect($businessSignatures)
    ->map(function ($signature) use ($usersById) {

        $signature['user'] = $usersById[$signature['user_id']] ?? null;

        return $signature;
    })
    ->values()
    ->toArray();


    return $enrichedParticipants;

    
}
}