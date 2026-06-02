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
        $context = $request->query("context", "");

        return $this->documentWorkflowService->getDocuments(
            [
                "userId" => $user["id"],
                "roleId" => $user["role_id"],
                "document_type" => $document_type,
                "context" => $context,
                "filters" => $request->query("filters"),
            ],
            $request,
            $this->workflowPermissionService
        );
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
