<?php

namespace App\Http\Controllers;

use App\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use App\Services\DocumentWorkflowService;
use App\Services\WorkflowPermissionService;
use Exception;

class WorkflowValidationController extends Controller
{
    private $documentWorkflowService;
    private $workflowPermissionService;

    public function __construct(
        DocumentWorkflowService $documentWorkflowService,
        WorkflowPermissionService $workflowPermissionService
    ) {
        $this->documentWorkflowService = $documentWorkflowService;
        $this->workflowPermissionService = $workflowPermissionService;
    }


    public function getDocuments(Request $request)
    {
        $user = $request->get("user");

        $document_type = $request->query("document_type", []);
        $validationContext = $request->query("validationContext", "");
        $filters = $request->query("filters" , []);
        $currentPage = $request->query("currentPage" , 1);
        $per_page = $request->query("per_page" , 10);
        $isStat = (bool)$request->query("isStat" , 0);
        // $filterContext = $request->query("filterContext", "");
        // $filterContext = $filters["statut"];

        $filterContext = $this->resolveFilterContext(
    $validationContext,
    $filters
);

        return $this->documentWorkflowService->getDocuments(
            [
                "employeeId" => $user["employee_id"],
                "userId" => $user["id"],
                "roleId" => $user["role_id"],
                "document_type" => $document_type,
                "validationContext" => $validationContext,
                "filterContext" => $filterContext,
                "filters" => $filters,
                "currentPage" => $currentPage,
                "per_page" => $per_page,
                "isStat" => $isStat,
                
            ],
            $request,
            $this->workflowPermissionService
        );
    }


    private function resolveFilterContext(string $validationContext, array $filters): string
{
    $statut = $filters['statut'] ?? '';

    $mapping = [
        'TO_VALIDATE' => [
            ''          => 'ALL_DOCUMENTS',
            'ALL'          => 'ALL_DOCUMENTS',
            'PENDING'   => 'PENDING',
            'COMPLETE'  => 'COMPLETE',
        ],

        'MY_DOCUMENTS' => [
            ''            => 'ALL_DOCUMENTS',
            'ALL'            => 'ALL_DOCUMENTS',
            'IN_PROGRESS' => 'IN_PROGRESS',
            'COMPLETE'    => 'COMPLETE',
        ],
    ];

    return $mapping[$validationContext][$statut] 
        ?? 'ALL_DOCUMENTS';
}


    /**
     * Prépare les paramètres pour l'appel HTTP au service document.
     *
     * @param Collection|array $documentIds
     * @param array $documentTypes
     * @param array $filters
     * @return array
     */
    function prepareDocumentQueryParams(
        $documentIds,
        array $documentTypes = [],
        array $filters = []
    ): array {
        $params = [];

        // Encodage des IDs comme tableau ou CSV
        $params["ids"] =
            $documentIds instanceof \Illuminate\Support\Collection
                ? $documentIds->toArray()
                : $documentIds;

        // Document types
        if (!empty($documentTypes)) {
            $params["documentTypes"] = $documentTypes;
        }

        // Filtres dynamiques
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                // si c'est un tableau (ex: plusieurs statuts), on peut envoyer en CSV
                $params[$key] = implode(",", $value);
            } else {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
