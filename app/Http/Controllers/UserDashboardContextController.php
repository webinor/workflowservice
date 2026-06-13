<?php

namespace App\Http\Controllers;

use App\Models\DocumentTypeWorkflow;
use App\Models\Signature;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;

class UserDashboardContextController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->get("user")["id"];
        $roles = $request->input("roles", []);
        $departmentId = $request->input("department_id");

        // 1. Récupérer workflows assignés à ces rôles
        $tasks = WorkflowInstanceStep::query()
            ->whereHas("assignments", function ($q) use ($roles, $userId) {
                $q->where("user_id", $userId)->orWhereIn("role_id", $roles);
                $q->where("source_type", "!=" , "OWNER");
            })
            ->where("status", "PENDING")
            ->with(["workflowStep.workflow"])
            ->get();

        $workflowIds = $tasks
            ->pluck("workflowStep.workflow_id")
            ->unique()
            ->values()
            ->all();

        $mapping = DocumentTypeWorkflow::query()
            ->whereIn("workflow_id", $workflowIds)
            ->with("documentType")
            ->get()
            ->groupBy("workflow_id");

        $tasksByType = $tasks->groupBy(function ($step) use ($mapping) {
            $workflowId = $step->workflowStep->workflow_id;

            $docType = $mapping[$workflowId][0]->documentType ?? null;

            return $docType ? $docType->id : "unknown";
        });

        $tasks = $tasksByType
            ->map(function ($steps, $type) {
                return [
                    "document_type" => $type,
                    "count" => $steps->count(),
                    "can_validate" => true,
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
        ]);
    }
}
