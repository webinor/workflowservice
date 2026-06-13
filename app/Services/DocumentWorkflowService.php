<?php

namespace App\Services;

use App\Models\Signature;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\DocumentEnricherRegistry;
use App\Services\Workflow\WorkflowInstanceResolverService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocumentWorkflowService
{

    protected WorkflowInstanceResolverService $resolver;
    protected DocumentEnricherRegistry $registry;

    const CONTEXT_VALIDATION = 'TO_VALIDATE';
    const CONTEXT_MY_DOCUMENTS = 'MY_DOCUMENTS';

    public function __construct(WorkflowInstanceResolverService $workflowInstanceResolverService,
    DocumentEnricherRegistry $documentEnricherRegistry
    ) {
        $this->resolver = $workflowInstanceResolverService;
        $this->registry = $documentEnricherRegistry;
    }

//     public function getDocumentsToValidateByRole(
//     Request $request,
//     array $documentTypes,
//     WorkflowPermissionService $workflowPermissionService
// ): array {
//      $user = $request->get('user');

//     return $this->getDocumentsForUser([
//         'userId'        => $user['id'],
//         'roleId'        => $user['role_id'],
//         'documentTypes' => $documentTypes,
//         'filters'       => $request->query('filters'),
//     ], $request , $workflowPermissionService);
// }


    public function getDocuments(
    array $params,
    Request $request,
    WorkflowPermissionService $permissionService
): array {

//     return $this->getDocumentsForUser($params, $request , $permissionService);
// }



//     public function getDocumentsForUser(array $params, Request $request, WorkflowPermissionService $workflowPermissionService)
// {
    [
        'userId'        => $userId,
        'roleId'        => $roleId,
        'document_type' => $document_type,
        'filters'       => $filters,
        'context'       => $context,
    ] = $params;

    // 1. IDs selon contexte
    $documentIds = $this->getDocumentIds($roleId, $userId, $context, $params["filters"]);



    if ($documentIds->isEmpty()) {
        return [];
    }

    // 2. documents
    $documents = $this->fetchDocuments(
        $documentIds,
        $document_type,
        $filters,
        $request
    );


    $availabilityContexts = $this->availabilityContexts($documentIds->toArray());

    

    $contextsByDocId = collect($availabilityContexts)
    ->keyBy('document_id');

    $documents = collect($documents)->map(function ($doc) use ($contextsByDocId) {

    $context = $contextsByDocId[$doc['id']] ?? null;

    return $this->enrichDocument($doc, $context);

    })->toArray();

    //  throw new Exception(json_encode($documents), 1);


    if (empty($documents)) {
        return [];
    }

    // 3. permissions (identique)
    $permissionsByDocType = $this->getPermissions(
        $documents,
        $userId,
        $roleId,
        $request,
        $permissionService
    );

    // 4. workflow instances
    $workflowInstances = WorkflowInstance::whereIn('document_id', $documentIds)
        ->get()
        ->keyBy('document_id');

    // 5. steps actionnables
    // $actionableSteps = WorkflowInstanceStep::where('role_id', $roleId)
    //     ->where('status', 'PENDING')
    //     ->get()
    //     ->keyBy('workflow_instance_id');

    $actionableSteps = WorkflowInstanceStep::whereHas('assignments', function ($q) use ($roleId) {
        $q->where('role_id', $roleId)
          ->where('decision', 'PENDING');
    })
    ->where('status', 'PENDING')
    ->with('assignments')
    ->get()
    ->keyBy('workflow_instance_id');

    // 6. enrichissement unique
    return $this->enrichDocuments(
        $documents,
        $permissionsByDocType,
        $workflowInstances,
        $actionableSteps,
        $userId,
        $context
    );
}


private function enrichDocument(array $doc, ?array $context): array
{
    
    // throw new Exception(json_encode($doc), 1);

    $resolver = $this->registry->resolve($doc['document_type_slug']);

    

    return $resolver->enrich($doc, $context);
}


public function availabilityContexts(array $documentIds)
{
    // 1. Récupérer tous les workflows en une fois
    $workflows = WorkflowInstance::query()
        ->whereIn('document_id', $documentIds)
        ->get()
        ->keyBy('document_id');

    // 2. Signatures en batch
    $signatures = Signature::query()
        ->whereIn('document_id', $documentIds)
        ->with('signatureType')
        ->get()
        ->groupBy('document_id');

    // 3. Steps en batch (via workflow_instance_id)
    $workflowIds = $workflows->pluck('id')->toArray();

    $steps = WorkflowInstanceStep::query()
        ->whereIn('workflow_instance_id', $workflowIds)
        ->where('status', 'COMPLETE')
        ->with('workflowStep')
        ->get()
        ->groupBy('workflow_instance_id');

    // 4. Build response
    return collect($documentIds)->map(function ($documentId) use (
        $workflows,
        $signatures,
        $steps
    ) {
        $workflow = $workflows[$documentId] ?? null;

        if (!$workflow) {
            return [
                "document_id" => $documentId,
                "workflow_status" => null,
                "signatures" => [],
                "completed_steps" => []
            ];
        }

        $docSignatures = ($signatures[$documentId] ?? collect())
            ->map(function ($signature) {
                return [
                    "code" => $signature->signatureType->code,
                    "signed" => true,
                    "signed_at" => $signature->signed_at,
                ];
            })
            ->values();

        $docSteps = ($steps[$workflow->id] ?? collect())
            ->map(fn($step) => $step->workflowStep->code)
            ->values();

        return [
            "document_id" => $documentId,
            "workflow_status" => $workflow->status,
            "signatures" => $docSignatures,
            "completed_steps" => $docSteps,
        ];
    })->values()->toArray();
}

public function availabilityContext(int $documentId)
{
    $workflow = WorkflowInstance::where(
        "document_id",
        $documentId
    )
    ->first();

    if (!$workflow) {
        return response()->json([
            "workflow_status" => null,
            "signatures" => [],
            "completed_steps" => []
        ]);
    }

    $signatures = Signature::query()
        ->where("document_id", $documentId)
        ->with("signatureType")
        ->get()
        ->map(function ($signature) {

            return [
                "code" => $signature->signatureType->code,
                "signed" => true,
                "signed_at" => $signature->signed_at,
            ];
        });

    $completedSteps = WorkflowInstanceStep::query()
        ->where(
            "workflow_instance_id",
            $workflow->id
        )
        ->where("status", "COMPLETE")
        ->with("workflowStep")
        ->get()
        ->map(fn($step) => $step->workflowStep->code)
        ->values();

    return response()->json([
        "workflow_status" => $workflow->status,
        "signatures" => $signatures,
        "completed_steps" => $completedSteps,
    ]);
}

    /* ======================= HELPERS ======================= */

    protected function oldgetDocumentIds(int $roleId)
    {

        $query = WorkflowInstanceStep::with('workflowInstance');

        return $query
            ->get()
            ->pluck('workflowInstance.document_id')
            ->filter()
            ->unique()
            ->values();
    }

   protected function getDocumentIds(
    int $roleId,
    int $userId,
    string $context,
    $filters
) {
    $query = WorkflowInstanceStep::query()
        ->with('workflowInstance');

    $filters = $filters ?? [];

    $statut = $filters['statut'] ?? null;
    $date = $filters['date'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | CONTEXT : TO_VALIDATE
    |--------------------------------------------------------------------------
    */
    if ($context === self::CONTEXT_VALIDATION) {



    // Filtre optionnel sur le workflow
    if (!empty($statut)) {

        // L'étape doit être en attente
    $query->where('status', $statut);

    // Et assignée à l'utilisateur / rôle courant
    $query->whereHas('assignments', function ($q) use ($roleId) {
        $q->where('role_id', $roleId);
    });

        $query->whereHas('workflowInstance', function ($q) use ($statut) {
            $q->where('status', $statut);
        });
    }
}

    /*
    |--------------------------------------------------------------------------
    | CONTEXT : MY DOCUMENTS
    |--------------------------------------------------------------------------
    */
    if ($context === self::CONTEXT_MY_DOCUMENTS) {

        $query->whereHas('workflowInstance', function ($q) use ($userId, $statut) {

            // $q->where('initiator_user_id', $userId);

            if (!empty($statut)) {
                $q->where('status', $statut);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DATE FILTER (DOCUMENT SIDE — si dispo via relation)
    |--------------------------------------------------------------------------
    */
    if (!empty($date['from']) && !empty($date['to'])) {

        $query->whereHas('workflowInstance.document', function ($q) use ($date) {
            $q->whereBetween('created_at', [
                $date['from'],
                $date['to']
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */
    return $query
        ->get()
        ->pluck('workflowInstance.document_id')
        ->filter()
        ->unique()
        ->values();
}

    protected function fetchDocuments(
        $documentIds,
        array $documentTypes,
        ?array $filters,
        Request $request
    ): array {
        $response = Http::withToken($request->bearerToken())
            ->acceptJson()
            ->get(
                config('services.document_service.base_url') . '/by-ids',
                [
                    'ids' => $documentIds->toArray(),
                    'documentTypes' => $documentTypes,
                    'filters' => $filters,
                ]
            );

        // throw new Exception(json_encode($response->body()), 1);
        // throw new Exception(json_encode($documentTypes), 1);

        if ($response->ok()) {
            return $response->json();
        } else {
            
        throw new Exception(json_encode($response->body()), 1);


        }
        
        

        // return $response->ok() ? $response->json() : [];
    }


    protected function getPermissions(
        array $documents,
        int $userId,
        int $roleId,
        Request $request,
        WorkflowPermissionService $workflowPermissionService
    ) {
        $data = [
            'user_id' => $userId,
            'role_id' => $roleId,
            'count'   => count($documents),
            'documents' => $documents,
        ];

        $permissions = $workflowPermissionService->checkPermissions2($data , $request);
        
        // app()->call(
        //     'App\Http\Controllers\WorkflowValidationController@checkPermissions2',
        //     ['data' => $data, 'request' => $request]
        // );

        return collect($permissions)->keyBy('documentId');
    }



    protected function enrichDocuments(
        array $documents,
        $permissionsByDocType,
        $workflowInstances,
        $actionableSteps,
        int $userId,
        string $context
    ): array {
        $translations = $this->statusTranslations();

        // throw new Exception(json_encode($permissionsByDocType), 1);
        

        return collect($documents)
            ->filter(fn ($doc) => $this->canView($doc, $permissionsByDocType, $userId , $context))
            ->map(function ($doc) use ($workflowInstances, $actionableSteps, $translations) {

                $instance = $workflowInstances[$doc['id']] ?? null;

                $doc['workflow_status'] = null;
                $doc['can_validate'] = false;

                if ($instance) {
                    $doc['workflow_status'] = $translations[$instance->status] ?? null;
                    $doc['workflow_label'] =   $this->resolver->resolveWorkflowStatusLabel($instance) ?? "N/D";
                    $doc['can_validate'] = isset(
                        $actionableSteps[$instance->id]
                    );
                }

                return $doc;
            })
            ->values()
            ->toArray();
    }

    protected function canView(
    array $doc,
    $permissionsByDocType,
    int $userId,
    string $context 
): bool {

    $perm = $permissionsByDocType[$doc['document_type_id']] ?? null;

    if (!$perm) {
        return false;
    }

    $permissions = $perm['permissions'];

    $isOwner = ($doc['created_by'] === $userId);

    $isSameDepartment = $this->checkSameDepartment(
        $doc['created_by'],
        $userId
    );

    /**
     * =========================
     * 📁 MES DOCUMENTS
     * =========================
     */
    if ($context === 'MY_DOCUMENTS') {
        return $isOwner;
    }

    /**
     * =========================
     * 🧾 À VALIDER
     * =========================
     */
    if ($context === 'TO_VALIDATE') {

        return (
            ($permissions['view_department'] && $isSameDepartment)
            || $permissions['view_all']
        );
    }

    /**
     * =========================
     * 🌍 ALL DOCUMENTS
     * =========================
     */
    if ($context === 'ALL_DOCUMENTS') {

        return (
            $permissions['view_all']
            || ($permissions['view_department'] && $isSameDepartment)
            || ($permissions['view_own'] && $isOwner)
        );
    }

    return false;
}

    protected function old_canView(array $doc, $permissionsByDocType, int $userId): bool
    {
        $perm = $permissionsByDocType[$doc['document_type_id']] ?? null;

        if (!$perm) {
            return false;
        }

        return
            $perm['permissions']['view_all'] ||
            (
                $perm['permissions']['view_own'] &&
                $doc['created_by'] === $userId
            )
            || 
            
            (
                $perm['permissions']['view_department'] &&
                $this->checkSameDepartment($doc['created_by'] , $userId)
            );
            ;
    }

    protected function checkSameDepartment($user_1,$user_2) : bool {


$response = Http::get(config("services.department_service.base_url") . '/users/same-department', [
    'user1_id' => $user_1,
    'user2_id' => $user_2,
]);

$data = $response->json();

// throw new Exception(json_encode($data), 1);


if ($data['same_department']) {
    // Ils sont dans le même département
    return true;
} else {
    // Pas dans le même département
    return false;
}
        
    }

    protected function statusTranslations(): array
    {
        return [
            'NOT_STARTED' => [
                'label' => 'Validation non démarrée',
                'emoji' => '⏳',
                'color' => 'info',
            ],
            'PENDING' => [
                'label' => 'En cours de validation',
                'emoji' => '🟡',
                'color' => 'warning',
            ],
            'COMPLETE' => [
                'label' => 'Validation terminée',
                'emoji' => '✅',
                'color' => 'success',
            ],
            'REJECT' => [
                'label' => 'Rejetée',
                'emoji' => '❌',
                'color' => 'error',
            ],
        ];
    }
}
