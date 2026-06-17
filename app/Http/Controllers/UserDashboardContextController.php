<?php

namespace App\Http\Controllers;

use App\Models\DocumentTypeWorkflow;
use App\Models\Signature;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStep;
use App\Services\HttpClientService;
use Exception;
use Illuminate\Http\Request;

class UserDashboardContextController extends Controller
{
    public function show(Request $request )
    {
        $userId = $request->get("user")["id"];
        $roles = $request->input("roles", []);
        $departmentId = $request->input("department_id");

        // 1. Récupérer workflows assignés à ces rôles
        // $tasks = WorkflowInstanceStep::query()
        //     ->whereHas("assignments", function ($q) use ($roles, $userId) {
        //         $q->where("user_id", $userId)->orWhereIn("role_id", $roles);
        //         $q->where("source_type", "!=" , "OWNER");
        //     })
        //     ->where("status", "PENDING")
        //     ->with(["workflowStep.workflow"])
        //     ->get();

      $excludedRules = [
    "MISSION_EXECUTOR",
    "MISSION_OWNER",
    "REQUESTER",
    "BENEFICIARY",
];


;

$tasks = WorkflowInstanceStep::query()
    ->whereHas("assignments", function ($q) use ($roles, $userId) {
        $q->where(function ($sub) use ($roles, $userId) {
            $sub->where("user_id", $userId)
                ->orWhereIn("role_id", $roles);
        })
        ->where("source_type", "!=", "OWNER");
    })
    ->where("status", "PENDING")
    ->with(["workflowStep.workflow", "workflowStep"])
    ->get();

    // throw new Exception(json_encode($tasks), 1);

    $isValidatorUser = !$tasks->contains(function ($step) use ($excludedRules) {

    $rule = $step->workflowStep->assignment_rule;

    return in_array($rule, $excludedRules);
});

    // throw new Exception(json_encode($workflowContext), 1);
    // throw new Exception(json_encode($isValidatorUser), 1);


       $workflowIds = $tasks
    ->pluck("workflowStep.workflow_id")
    ->unique()
    ->values()
    ->all();

$mapping = DocumentTypeWorkflow::query()
    ->whereIn("workflow_id", $workflowIds)
    ->get()
    ->groupBy("workflow_id");

    $documentTypeIds = $mapping
    ->flatten()
    ->pluck("document_type_id")
    ->unique()
    ->values()
    ->all();

    $client = HttpClientService::service('document');

    $response = $client->get("documentTypes", ["ids" => $documentTypeIds]);

    throw new Exception(json_encode($response), 1);

    $documentTypes = $documentTypeService->findMany($documentTypeIds);
        

        $tasksByType = $tasks->groupBy(function ($step) use ($mapping) {
            $workflowId = $step->workflowStep->workflow_id;

            $docType = $mapping[$workflowId][0]->documentType ?? null;

            if ($docType) {
                return $docType->id;
            }
            else{
    throw new Exception(json_encode($mapping[$workflowId]), 1);

            }

    throw new Exception(json_encode("Type de document inconnu"), 1);

        });

        $tasks = $tasksByType
            ->map(function ($steps, $type) use ($isValidatorUser) {
                return [
                    "document_type" => $type,
                    "count" => $steps->count(),
                    "can_validate" => $isValidatorUser,
                ];
            })
            ->values();

        // 2. Signatures (employee-based)
        $signatures = Signature::query()
            ->where("employee_id", $request->input("employee_id"))
            ->with("signatureType")
            ->get()
            ->map(
                fn($s) => [
                    "code" => $s->signatureType->code,
                    "signed" => true,
                    "signed_at" => $s->signed_at,
                ]
            );

        // 3. Availability globale
        return response()->json([
            "user_id" => $userId,
            "tasks" => $tasks,
            "signatures" => $signatures,
            "has_pending_tasks" => $tasks->isNotEmpty(),
            "isValidatorUser" => $isValidatorUser
        ]);
    }
}
