<?php

namespace App\Services\Workflow\Participant;

use App\Models\WorkflowInstance;
use App\Services\HttpClientService;
use App\Services\Actor\ActorEnricher;
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

//   throw new \Exception(json_encode($businessSignatures), 1);
  

     $participants =  $participantResolver->resolve($instance);


     
     $participants = app(UserEnricher::class)->enrich($participants);

//   throw new \Exception(json_encode($participants), 1);


    
$businessSignatures = app(ActorEnricher::class)
    ->enrich($businessSignatures , "actor_id");

    return ["participants"=>$participants,
    "business_signatures"=>$businessSignatures];


    

    
}
}