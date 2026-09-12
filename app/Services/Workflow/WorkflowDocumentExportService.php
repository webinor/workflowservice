<?php

namespace App\Services\Workflow;

use App\Services\Document\DocumentServiceClient;
use App\Services\DocumentWorkflowService;
use App\Services\WorkflowPermissionService;
use Illuminate\Http\Request;

class WorkflowDocumentExportService
{
    protected DocumentWorkflowService $documentWorkflowService;

    protected DocumentServiceClient $documentServiceClient;

    protected WorkflowPermissionService $workflowPermissionService;

    public function __construct(
        DocumentWorkflowService $documentWorkflowService,
        DocumentServiceClient $documentServiceClient,
        WorkflowPermissionService $workflowPermissionService
    ) {
        $this->documentWorkflowService = $documentWorkflowService;
        $this->documentServiceClient = $documentServiceClient;
        $this->workflowPermissionService = $workflowPermissionService;
    }

    /**
     * Sélectionne les documents autorisés par workflow-service
     * puis délègue la génération du fichier au document-service.
     */
    public function export(
        Request $request,
        array $filterContext
    ) {
        $user = $request->get('user');

        $documentType = $request->input(
            'document_type'
        );

        $context = $request->input(
            'context'
        );

        $filters = $request->input(
            'filters',
            []
        );

        $columns = $request->input(
            'columns',
            []
        );

        /*
         * IMPORTANT :
         *
         * On reprend exactement le contexte utilisateur
         * utilisé par getDocuments().
         */
        $result = $this->documentWorkflowService->getDocuments(
            [
                'employeeId' => $user['employee_id'],
                'userId' => $user['id'],
                'roleId' => $user['role_id'],

                'document_type' => [
                    $documentType,
                ],

                'validationContext' => $context,

                'filterContext' => $filterContext,

                'filters' => $filters,

                /*
                 * Pas de pagination pour un export.
                 */
                'currentPage' => 1,
                'per_page' => 1,

                'isStat' => false,

                /*
                 * Nouveau mode.
                 */
                'export' => true,
            ],
            $request,
            $this->workflowPermissionService
        );

        $documents = isset($result['data'])
            ? $result['data']
            : [];

        /*
         * IDs effectivement visibles après toutes les règles
         * workflow + permissions + visibilité.
         */
        $documentIds = collect($documents)
            ->pluck('id')
            ->values()
            ->all();

        /*
         * Le workflow-status appartient au workflow-service.
         *
         * On le transmet explicitement au document-service.
         */
        $workflowMetadata = [];

        foreach ($documents as $document) {
            if (!isset($document['id'])) {
                continue;
            }

            $workflowMetadata[$document['id']] = [
                'workflow_status' => isset(
                    $document['workflow_status']
                )
                    ? $document['workflow_status']
                    : null,
            ];
        }

        /*
         * Aucun document autorisé.
         *
         * Le document-service peut éventuellement gérer ce cas
         * si nous décidons d'autoriser un fichier vide.
         */
        if (empty($documentIds)) {
            return response()->json([
                'message' => 'Aucun document à exporter.',
            ], 422);
        }

        /*
         * Le document-service prend maintenant en charge
         * toute la génération Excel.
         */
        $response = $this->documentServiceClient->exportExcel(
            $documentIds,
            $documentType,
            $columns,
            $workflowMetadata,
            $request
        );

        /*
         * On restitue directement le fichier généré.
         */
        return response(
            $response->body(),
            $response->status()
        )
            ->withHeaders([
                'Content-Type' => $response->header(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ),
                'Content-Disposition' => $response->header(
                    'Content-Disposition'
                ),
            ]);
    }
}