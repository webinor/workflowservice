<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Exception;

class DocumentWorkflowService
{

    public function getDocumentsToValidateByRole(
    Request $request,
    array $documentTypes,
    WorkflowPermissionService $workflowPermissionService
): array {
     $user = $request->get('user');

    return $this->getDocumentsForUser([
        'userId'        => $user['id'],
        'roleId'        => $user['role_id'],
        'documentTypes' => $documentTypes,
        'filters'       => $request->query('filters'),
    ], $request , $workflowPermissionService);
}
    public function getDocumentsForUser(array $params, Request $request , WorkflowPermissionService $workflowPermissionService)//: array
    {
        [
            'userId'        => $userId,
            'roleId'        => $roleId,
            'documentTypes' => $documentTypes,
            'filters'       => $filters,
        ] = $params;

        // 1️⃣ Récupération des documentIds
        $documentIds = $this->getDocumentIds($roleId);

        if ($documentIds->isEmpty()) {
            return [];
        }

        // 2️⃣ Récupération des documents
        $documents = $this->fetchDocuments(
            $documentIds,
            $documentTypes,
            $filters,
            $request
        );

        if (empty($documents)) {
            return [];
        }

        // 3️⃣ Permissions
            $permissionsByDocType = $this->getPermissions(
            $documents,
            $userId,
            $roleId,
            $request,
            $workflowPermissionService
        );

        // 4️⃣ Workflow instances
        $workflowInstances = WorkflowInstance::whereIn(
            'document_id',
            $documentIds
        )->get()->keyBy('document_id');

        // 5️⃣ Steps actionnables
        $actionableSteps = WorkflowInstanceStep::where('role_id', $roleId)
            ->where('status', 'PENDING')
            ->get()
            ->keyBy('workflow_instance_id');

        // 6️⃣ Enrichissement final
        return $this->enrichDocuments(
            $documents,
            $permissionsByDocType,
            $workflowInstances,
            $actionableSteps,
            $userId
        );
    }

    /* ======================= HELPERS ======================= */

    protected function getDocumentIds(int $roleId)
    {

        $query = WorkflowInstanceStep::with('workflowInstance');



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

        return $response->ok() ? $response->json() : [];
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
        int $userId
    ): array {
        $translations = $this->statusTranslations();

        return collect($documents)
            ->filter(fn ($doc) => $this->canView($doc, $permissionsByDocType, $userId))
            ->map(function ($doc) use ($workflowInstances, $actionableSteps, $translations) {

                $instance = $workflowInstances[$doc['id']] ?? null;

                $doc['workflow_status'] = null;
                $doc['can_validate'] = false;

                if ($instance) {
                    $doc['workflow_status'] = $translations[$instance->status] ?? null;
                    $doc['can_validate'] = isset(
                        $actionableSteps[$instance->id]
                    );
                }

                return $doc;
            })
            ->values()
            ->toArray();
    }

    protected function canView(array $doc, $permissionsByDocType, int $userId): bool
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
