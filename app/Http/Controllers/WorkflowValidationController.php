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

    // public function getDocumentsToValidateByRole(Request $request)
    // {
    //     // $roleId = $request->get('role_id');
    //     $user_connected = $request->get("user"); // récupéré du user-service
    //     $userId = $user_connected["id"]; // récupéré du user-service
    //     $roleId = $user_connected["role_id"]; // récupéré du user-service

    //     $isValidation = filter_var(
    //         $request->query("isValidation"),
    //         FILTER_VALIDATE_BOOLEAN
    //     );

    //     $documentTypes = $request->query("documentTypes");
    //     $filters = $request->query("filters");

    //     // 1️⃣ Récupérer toutes les étapes en attente pour ce rôle
    //     // if ($isValidation) {
    //     //     $steps = WorkflowInstanceStep::with("workflowInstance")
    //     //         ->where("role_id", $roleId)
    //     //         ->where("status", "PENDING")
    //     //         ->get();
    //     // } else {
    //     //     //si c'est juste le suivi

    //     //     $steps = WorkflowInstanceStep::with("workflowInstance")
    //     //         //  ->where('role_id', $roleId)
    //     //         // ->where('status', 'PENDING')
    //     //         ->get();
    //     // }
    //     $stepsRoleQuery = WorkflowInstanceStep::with(
    //         "workflowInstance:id,document_id,status",
    //         "workflowStep:id,workflow_status_label_id"
    //     )
    //         ->where("role_id", $roleId)
    //         ->where("status", "PENDING");

    //     $allStepsQuery = WorkflowInstanceStep::with(
    //         "workflowInstance:id,document_id,status",
    //         "workflowStep:id,workflow_status_label_id"
    //     );

    //     $steps_role = $stepsRoleQuery->get();
    //     $all_steps = $allStepsQuery->get();

    //     // 2️⃣ Extraire les document_ids
    //     $documentIds = $all_steps
    //         ->pluck("workflowInstance.document_id")
    //         ->unique();

    //     // return $documentIds->toArray();

    //     // 3️⃣ Appeler le microservice Document pour récupérer les détails
    //     $documents = [];
    //     if ($documentIds->isNotEmpty()) {
    //         //  return  $queryParams = $this->prepareDocumentQueryParams($documentIds, $documentTypes, $filters);

    //         // config('services.document_service.base_url');
    //         //return
    //         $response = Http::withToken($request->bearerToken())
    //             ->acceptJson()
    //             ->get(
    //                 config("services.document_service.base_url") . "/by-ids", //$queryParams
    //                 /**/ [
    //                     "ids" => $documentIds->toArray(),
    //                     "documentTypes" => $documentTypes,
    //                     "filters" => $filters,
    //                 ] /**/
    //             );

    //         if ($response->ok()) {
    //             $documents = $response->json();
    //         }
    //     }

    //     if (count($documents) == 0) {
    //         return [];
    //     }

    //     //   return $documents;

    //     $data = [
    //         "user_id" => $userId,
    //         "role_id" => $roleId,
    //         "count" => count($documents),
    //         "documents" => $documents,
    //     ];

    //     $documents_with_permissions = $this->workflowPermissionService->checkPermissions2(
    //         $data,
    //         $request
    //     );

    //     // On indexe les permissions par documentId
    //     $permissionsByDocId = collect($documents_with_permissions)->keyBy(
    //         "documentId"
    //     );

    //     // Récupérer les instances de workflow correspondantes
    //     $workflowInstances = WorkflowInstance::whereIn(
    //         "document_id",
    //         $documentIds
    //     )
    //         ->with(["lastActiveStep", "workflowStatusLabel"])
    //         ->get()
    //         ->keyBy("document_id"); // clé = document_id pour accès rapide

    //     // On filtre et on enrichit les documents
    //     $translations = [
    //         "NOT_STARTED" => [
    //             "label" => "Validation non démarrée",
    //             "emoji" => "⏳",
    //             "color" => "info",
    //         ],
    //         "PENDING" => [
    //             "label" => "Validation En Cours",
    //             "emoji" => "🟡",
    //             "color" => "warning",
    //         ],
    //         "COMPLETE" => [
    //             "label" => "Payée",
    //             "emoji" => "✅",
    //             "color" => "success",
    //         ],
    //         "REJECT" => [
    //             "label" => "Rejetée",
    //             "emoji" => "❌",
    //             "color" => "error",
    //         ],
    //     ];

    //     $filtered = collect($documents)
    //         ->filter(function ($doc) use ($permissionsByDocId, $steps_role) {
    //             $docId = $doc["id"];

    //             // Permissions pour ce document
    //             $docPermissions =
    //                 $permissionsByDocId[$doc["document_type_id"]] ?? null;
    //             if (!$docPermissions) {
    //                 return false;
    //             }

    //             // Si l'utilisateur a view_all, on garde le document
    //             if ($docPermissions["permissions"]["view_all"] ?? false) {
    //                 return true;
    //             }

    //             // Si l'utilisateur a view_own, on garde seulement s'il a un step pour ce document
    //             if ($docPermissions["permissions"]["view_own"] ?? false) {
    //                 return $steps_role->contains(function ($step) use ($docId) {
    //                     return $step->workflowInstance->document_id == $docId;
    //                 });
    //             }

    //             // Sinon, pas de permission → on exclut
    //             return false;
    //         })
    //         ->map(function ($doc) use ($workflowInstances, $translations) {
    //             $workflow_instance = $workflowInstances[$doc["id"]] ?? null;
    //             $status = $workflow_instance
    //                 ? $workflow_instance->status
    //                 : null;

    //             // $currentStep = $workflow_instance? $workflow_instance->instance_steps->first() : null ;
    //             $currentStep = $workflow_instance
    //                 ? $workflow_instance->lastActiveStep
    //                 : null;

    //             // $statusLabel = ($currentStep && $currentStep->workflowStep)? $currentStep->workflowStep->workflow_status_label_id : null;
    //             $statusLabel =
    //                 $currentStep && $currentStep->workflowStep
    //                     ? $currentStep->workflowStep->workflow_status_label_id
    //                     : null;

    //             if ($status && isset($translations[$status])) {
    //                 $doc["workflow_status"] = [
    //                     // "label" => $translations[$status]["label"],//$statusLabel ? $statusLabel : $translations[$status]["label"],
    //                     // "emoji" => $translations[$status]["emoji"],
    //                     // "color" => $translations[$status]["color"],

    //                     "label" => $workflow_instance->workflowStatusLabel
    //                         ? $workflow_instance->workflowStatusLabel->label
    //                         : "", // $statusLabel ? $statusLabel : $translations[$status]["label"],
    //                     "emoji" => $workflow_instance->workflowStatusLabel
    //                         ? $workflow_instance->workflowStatusLabel->emoji
    //                         : "", // $translations[$status]["emoji"],
    //                     "color" => $workflow_instance->workflowStatusLabel
    //                         ? $workflow_instance->workflowStatusLabel->color
    //                         : "", // $translations[$status]["color"],
    //                 ];
    //             } else {
    //                 $doc["workflow_status"] = null;
    //             }

    //             return $doc;
    //         })
    //         ->values()
    //         ->toArray();

    //     return $filtered;

     
    // }

    // public function getMyTaxiPapersToValidateByRole(Request $request)
    // {
    //             // $roleId = $request->get('role_id');
    //             $user_connected = $request->get("user"); // récupéré du user-service
    //             $userId = $user_connected["id"]; // récupéré du user-service
    //             $roleId = $user_connected["role_id"]; // récupéré du user-service
    //             $documentTypeSlug = "taxi_paper";

    //             $isValidation = false; 
                
            

    //             $documentTypes = ["taxi_paper"]; // $request->query("documentTypes");
    //             $filters = $request->query("filters");

    //             // 1️⃣ Récupérer toutes les étapes en attente pour ce rôle
    //             if ($isValidation) {
    //                 $steps = WorkflowInstanceStep::with("workflowInstance")
    //                     ->where("role_id", $roleId)
    //                     ->where("status", "PENDING")
    //                     ->get();
    //             } else {
    //                 //si c'est juste le suivi

    //                 $steps = WorkflowInstanceStep::with("workflowInstance")
    //                     //  ->where('role_id', $roleId)
    //                     // ->where('status', 'PENDING')
    //                     ->get();
    //             }

    //             // 2️⃣ Extraire les document_ids
    //             $documentIds = $steps->pluck("workflowInstance.document_id")->unique();

    //             // return $documentIds->toArray();

    //             // 3️⃣ Appeler le microservice Document pour récupérer les détails
    //             $documents = [];
    //             if ($documentIds->isNotEmpty()) {
    //                 //  return  $queryParams = $this->prepareDocumentQueryParams($documentIds, $documentTypes, $filters);

    //                 // config('services.document_service.base_url');

    //                 $response = Http::withToken($request->bearerToken())
    //                     ->acceptJson()
    //                     ->get(
    //                         config("services.document_service.base_url") . "/by-ids", //$queryParams
    //                         [
    //                             "ids" => $documentIds->toArray(),
    //                             "documentTypes" => $documentTypes,
    //                             "filters" => $filters,
    //                             "userId" => $userId,
    //                         ]
    //                     );

    //                 // new Exception(json_encode($response));

    //                 if ($response->ok()) {
    //                     $documents = $response->json();
    //                 }
    //             }

    //             if (count($documents) == 0) {
    //                 return [];
    //             }

    //             //   return $documents;

    //             $data = [
    //                 "user_id" => $userId,
    //                 "role_id" => $roleId,
    //                 "count" => count($documents),
    //                 "documents" => $documents,
    //             ];

    //             $documents_with_permissions = $this->workflowPermissionService->checkPermissions2(
    //                 $data,
    //                 $request
    //             );

    //             // On indexe les permissions par documentId
    //             $permissionsByDocId = collect($documents_with_permissions)->keyBy(
    //                 "documentId"
    //             );

    //             // Récupérer les instances de workflow correspondantes
    //             $workflowInstances = WorkflowInstance::whereIn(
    //                 "document_id",
    //                 $documentIds
    //             )
    //                 ->get()
    //                 ->keyBy("document_id"); // clé = document_id pour accès rapide

    //             // On filtre et on enrichit les documents
    //             $translations = [
    //                 "NOT_STARTED" => [
    //                     "label" => "Validation non démarrée",
    //                     "emoji" => "⏳",
    //                     "color" => "info",
    //                 ],
    //                 "PENDING" => [
    //                     "label" => "En cours de Traitement",
    //                     "emoji" => "🟡",
    //                     "color" => "warning",
    //                 ],
    //                 "COMPLETE" => [
    //                     "label" => "Approuvé",
    //                     "emoji" => "✅",
    //                     "color" => "success",
    //                 ],
    //                 "REJECT" => [
    //                     "label" => "Rejetée",
    //                     "emoji" => "❌",
    //                     "color" => "error",
    //                 ],
    //             ];

    //             $filtered = collect($documents)
    //                 ->filter(function ($doc) use ($permissionsByDocId) {
    //                     return isset($permissionsByDocId[$doc["document_type_id"]]) &&
    //                         ($permissionsByDocId[$doc["document_type_id"]][
    //                             "permissions"
    //                         ]["view_own"] === true ||
    //                             $permissionsByDocId[$doc["document_type_id"]][
    //                                 "permissions"
    //                             ]["view_all"] === true);
    //                 })
    //                 ->map(function ($doc) use ($workflowInstances, $translations) {
    //                     $instance = $workflowInstances[$doc["id"]] ?? null;
    //                     $status = $instance ? $instance->status : null;

    //                     if ($status && isset($translations[$status])) {
    //                         $doc["workflow_status"] = [
    //                             "label" => $translations[$status]["label"],
    //                             "emoji" => $translations[$status]["emoji"],
    //                             "color" => $translations[$status]["color"],
    //                         ];
    //                     } else {
    //                         $doc["workflow_status"] = null;
    //                     }

    //                     return $doc;
    //                 })
    //                 ->values()
    //                 ->toArray();

    //             return $filtered;


    //             // return response()->json();
    // }

    // public function getFeeNotes(Request $request)
    // {
    //     // $roleId = $request->get('role_id');
    //     $user_connected = $request->get("user"); // récupéré du user-service
    //     $userId = $user_connected["id"]; // récupéré du user-service
    //     $roleId = $user_connected["role_id"]; // récupéré du user-service

    //     return $this->documentWorkflowService->getDocumentsToValidateByRole(
    //         $request,
    //         ["fee_note"],
    //         $this->workflowPermissionService
    //     );
    // }

    // public function getMySumitedAbsenceRequests(Request $request)
    // {
    //     // $roleId = $request->get('role_id');
    //     $user_connected = $request->get("user"); // récupéré du user-service
    //     $userId = $user_connected["id"]; // récupéré du user-service
    //     $roleId = $user_connected["role_id"]; // récupéré du user-service

    //     $isValidation = false; /* filter_var(
    //         $request->query("isValidation"),
    //         FILTER_VALIDATE_BOOLEAN
    //     );*/

    //     $documentTypes = ["absence_request"]; // $request->query("documentTypes");
    //     $filters = $request->query("filters");

    //     // 1️⃣ Récupérer toutes les étapes en attente pour ce rôle
    //     if ($isValidation) {
    //         $steps = WorkflowInstanceStep::with("workflowInstance")
    //             ->where("role_id", $roleId)
    //             ->where("status", "PENDING")
    //             ->get();
    //     } else {
    //         //si c'est juste le suivi

    //         $steps = WorkflowInstanceStep::with("workflowInstance")
    //             ->get();
    //     }

    //     // 2️⃣ Extraire les document_ids
    //     $documentIds = $steps->pluck("workflowInstance.document_id")->unique();

    //     // return $documentIds->toArray();

    //     // 3️⃣ Appeler le microservice Document pour récupérer les détails
    //     $documents = [];
    //     if ($documentIds->isNotEmpty()) {
    //         //  return  $queryParams = $this->prepareDocumentQueryParams($documentIds, $documentTypes, $filters);

    //         // config('services.document_service.base_url');

    //         $response = Http::withToken($request->bearerToken())
    //             ->acceptJson()
    //             ->get(
    //                 config("services.document_service.base_url") . "/by-ids", //$queryParams
    //                 [
    //                     "ids" => $documentIds->toArray(),
    //                     "documentTypes" => $documentTypes,
    //                     "filters" => $filters,
    //                     "userId" => $userId,
    //                 ]
    //             );

    //         if ($response->ok()) {
    //             $documents = $response->json();
    //         }
    //     }

    //     if (count($documents) == 0) {
    //         return [];
    //     }

    //     //   return $documents;

    //     $data = [
    //         "user_id" => $userId,
    //         "role_id" => $roleId,
    //         "count" => count($documents),
    //         "documents" => $documents,
    //     ];

    //     $documents_with_permissions = $this->workflowPermissionService->checkPermissions2(
    //         $data,
    //         $request
    //     );

    //     // On indexe les permissions par documentId
    //     $permissionsByDocId = collect($documents_with_permissions)->keyBy(
    //         "documentId"
    //     );

    //     // Récupérer les instances de workflow correspondantes
    //     $workflowInstances = WorkflowInstance::whereIn(
    //         "document_id",
    //         $documentIds
    //     )
    //         ->get()
    //         ->keyBy("document_id"); // clé = document_id pour accès rapide

    //     // On filtre et on enrichit les documents
    //     $translations = [
    //         "NOT_STARTED" => [
    //             "label" => "Validation non démarrée",
    //             "emoji" => "⏳",
    //             "color" => "info",
    //         ],
    //         "PENDING" => [
    //             "label" => "En cours de validation",
    //             "emoji" => "🟡",
    //             "color" => "warning",
    //         ],
    //         "COMPLETE" => [
    //             "label" => "Validation terminée",
    //             "emoji" => "✅",
    //             "color" => "success",
    //         ],
    //         "REJECT" => [
    //             "label" => "Rejetée",
    //             "emoji" => "❌",
    //             "color" => "error",
    //         ],
    //     ];

    //     $filtered = collect($documents)
    //         ->filter(function ($doc) use ($permissionsByDocId) {
    //             return isset($permissionsByDocId[$doc["document_type_id"]]) &&
    //                 ($permissionsByDocId[$doc["document_type_id"]][
    //                     "permissions"
    //                 ]["view_own"] === true ||
    //                     $permissionsByDocId[$doc["document_type_id"]][
    //                         "permissions"
    //                     ]["view_all"] === true);
    //         })
    //         ->map(function ($doc) use ($workflowInstances, $translations) {
    //             $instance = $workflowInstances[$doc["id"]] ?? null;
    //             $status = $instance ? $instance->status : null;

    //             if ($status && isset($translations[$status])) {
    //                 $doc["workflow_status"] = [
    //                     "label" => $translations[$status]["label"],
    //                     "emoji" => $translations[$status]["emoji"],
    //                     "color" => $translations[$status]["color"],
    //                 ];
    //             } else {
    //                 $doc["workflow_status"] = null;
    //             }

    //             return $doc;
    //         })
    //         ->values()
    //         ->toArray();

    //     return $filtered;

   

    //     // return response()->json();
    // }

    // public function getMissionsToValidateByRole(Request $request)
    // {
    //     return $this->documentWorkflowService->getDocumentsToValidateByRole(
    //         $request,
    //         ["mission"],
    //         $this->workflowPermissionService
    //     );
    // }

    // public function getTaxiPapersToValidateByRole(Request $request)
    // {
    //     return $this->documentWorkflowService->getDocumentsToValidateByRole(
    //         $request,
    //         ["taxi_paper"],
    //         $this->workflowPermissionService
    //     );
    // }

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

    // public function getFeeNotesToValidateByRole(Request $request)
    // {
    //     return $this->documentWorkflowService->getDocumentsToValidateByRole(
    //         $request,
    //         ["fee_note"],
    //         $this->workflowPermissionService
    //     );
    // }

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
