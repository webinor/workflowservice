<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkflowInstanceStep;

class WorkflowValidationController extends Controller
{
    public function getDocumentsToValidateByRole(Request $request)
    {
       // $roleId = $request->get('role_id');
       $user_connected= $request->get('user'); // récupéré du user-service
       $userId= $user_connected['id']; // récupéré du user-service
       $roleId= $user_connected['role_id']; // récupéré du user-service

        // 1️⃣ Récupérer toutes les étapes en attente pour ce rôle
           $steps = WorkflowInstanceStep::with('workflowInstance')
            ->where('role_id', $roleId)
            ->where('status', 'PENDING')
            ->get();

        // 2️⃣ Extraire les document_ids
          $documentIds = $steps->pluck('workflowInstance.document_id')->unique();

       // return $documentIds->toArray();

        // 3️⃣ Appeler le microservice Document pour récupérer les détails
        $documents = [];
        if ($documentIds->isNotEmpty()) {
           // config('services.document_service.base_url'); 
             $response = Http::withToken($request->bearerToken())
            ->acceptJson()->get(config('services.document_service.base_url') . '/by-ids', [
                'ids' => $documentIds->toArray()
            ]);

            if ($response->ok()) {
                $documents = $response->json();
            }
        }


        if (count($documents) == 0) {
            return [];
        }

           $data = [
            'user_id'=>$userId,
            'role_id' => $roleId,
            'count' => count($documents),
            'documents' => $documents,
        ];

            $documents_with_permissions =  $this->checkPermissions2( $data , $request);

    // On indexe les permissions par documentId
      $permissionsByDocId = collect($documents_with_permissions)->keyBy('documentId');

// On filtre les documents
$filtered = collect($documents)->filter(function ($doc) use ($permissionsByDocId) {
    return  isset($permissionsByDocId[$doc['document_type_id']]) 
        && $permissionsByDocId[$doc['document_type_id']]['permissions']['view'] === true
        ;
})->values()->toArray();

        return($filtered);


       // return response()->json();
    }

    public function checkPermissions(array $rawDocuments , $request)
{
    // On récupère le userId (par ex. du document ou du contexte connecté)
    $userId = $rawDocuments['documents'][0]['created_by'];

    // On génère le payload (grâce à la fonction qu’on a faite avant)
    $payload = $this->transformToPayload($rawDocuments, $rawDocuments['role_id'], ['view', 'validate']);
    //$payload = $this->transformToPayload($rawDocuments, $userId, ['view', 'validate']);

    // Appel vers userservice
    $response = Http::withToken($request->bearerToken())
    ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch-role', $payload);
   // ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch', $payload);

    if ($response->failed()) {
        throw new \Exception('Erreur lors de la vérification des permissions du workflow : ' . $response->body());
    }

    return $response->json();
}

public function checkPermissions2(array $rawDocuments , $request)
{
    // On récupère le userId (par ex. du document ou du contexte connecté)
    $userId = $rawDocuments['documents'][0]['created_by'];

    // On génère le payload (grâce à la fonction qu’on a faite avant)
    //$payload = $this->transformToPayload($rawDocuments, $rawDocuments['role_id'], ['view', 'validate']);
    $payload = $this->transformToPayload2($rawDocuments, $rawDocuments['user_id'], ['view', 'validate']);

    // Appel vers userservice
    $response = Http::withToken($request->bearerToken())
    ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch', $payload);
   // ->acceptJson()->post(config('services.user_service.base_url') . '/permissions/check-batch', $payload);

    if ($response->failed()) {
        throw new \Exception('Erreur lors de la vérification des permissions du workflow : ' . $response->body());
    }

    return $response->json();
}


function transformToPayload(array $raw, int $roleId, array $actions = ['view', 'validate'])
{
    return [
        'roleId' => $roleId,
       // 'userId' => $userId,
        'documents' => collect($raw['documents'] ?? [])->map(function ($doc) {
            return [
                'doc_id' => $doc['id'],
                'id' => $doc['document_type_id'],
                'type' => $doc['document_type']['name'] ?? 'Unknown'
            ];
        })->toArray(),
        'actions' => $actions
    ];
}

function transformToPayload2(array $raw, int $userId, array $actions = ['view', 'validate'])
{
    return [
      //  'roleId' => $roleId,
        'userId' => $userId,
        'documents' => collect($raw['documents'] ?? [])->map(function ($doc) {
            return [
                'doc_id' => $doc['id'],
                'id' => $doc['document_type_id'],
                'type' => $doc['document_type']['name'] ?? 'Unknown'
            ];
        })->toArray(),
        'actions' => $actions
    ];
}
}
