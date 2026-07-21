<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowActionStepRequest;
use App\Http\Requests\UpdateWorkflowActionStepRequest;
use App\Models\Signature;
use App\Models\SignatureType;
use App\Models\WorkflowActionStep;
use App\Models\WorkflowInstanceStep;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WorkflowActionStepController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Récupère toutes les actions d'une étape de workflow
     *
     * @param int $instanceStepId
     */
    // public function getActionsByStep(Request $request , int $documentId , WorkflowInstanceStep $instanceStep)
    // {
    //     $user = $request->get("user");
    //     $userId = $user["id"];
    //     $userRoleId = $user["role_id"];
    //     $result = [];

    //    $instanceStep->load(["workflowStep","workflowInstance.workflow.documentTypeWorkflow"]);

    //     $document_type_id = $instanceStep->workflowInstance->workflow->documentTypeWorkflow->document_type_id;

    //     $canViewAllAttachment = $this->userCanAccessDocument(
    //                 $userId,
    //                 $userRoleId,
    //                 (int) $document_type_id
    //    );

    //     // Récupère les actions avec leurs infos workflow et action

    //     if ($instanceStep->workflowStep->assignment_mode == "STATIC") {
    //         $stepActions = WorkflowActionStep::with([
    //             "workflowAction.workflowActionType",
    //             "workflowStep.stepRoles",
    //             "transition",
    //         ])
    //             ->where("workflow_step_id", $instanceStep->workflowStep->id)
    //             ->get();

    //         // Cas 2 : statique (roles dans step_roles)
    //         foreach ($stepActions as $actionStep) {
    //             //return $stepActions;
    //             foreach ($actionStep["workflowStep"]["stepRoles"] as $role) {
    //                 $result[] = [
    //                     "permission_required" =>
    //                         $actionStep["permission_required"],
    //                     "workflow_action_type" => $actionStep["workflowAction"]["workflowActionType"]["code"],
    //                     "role_id" => $role["role_id"], // depuis step_roles
    //                     "transition_type" =>
    //                         $actionStep["transition"]["type"] ?? null,
    //                     "workflow_action_name" =>
    //                         $actionStep["workflowAction"]["name"],
    //                     "workflow_action_label" =>
    //                         $actionStep["workflowAction"]["action_label"],
    //                 ];
    //             }
    //         }
    //     } else {
    //         $instanceStep = WorkflowInstanceStep::with([
    //             "roles", // → table instance_step_roles (role_id connus)
    //             "workflowStep.workflowActionSteps.workflowAction.workflowActionType", // → actions possibles depuis workflow_step
    //             "workflowStep.workflowActionSteps.transition",
    //         ])->findOrFail($instanceStep->id);

    //         // Cas 1 : dynamique (role_id directement dans instance_step)
    //         foreach (
    //             $instanceStep["workflowStep"]["workflowActionSteps"]
    //             as $actionStep
    //         ) {
    //             $result[] = [
    //                 "permission_required" => $actionStep["permission_required"],
    //                 "workflow_action_type" => $actionStep["workflowAction"]["workflowActionType"]["code"],
    //                 "role_id" => $instanceStep["role_id"], // direct depuis instance_step
    //                 "transition_type" =>
    //                     $actionStep["transition"]["type"] ?? null,
    //                 "workflow_action_name" =>
    //                     $actionStep["workflowAction"]["name"],
    //                 "workflow_action_label" =>
    //                     $actionStep["workflowAction"]["action_label"],
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         "success" => true,
    //         "data" => $result,
    //     ]);
    // }
    public function getActionsByStep(
        Request $request,
        int $documentId,
        WorkflowInstanceStep $instanceStep
    ) {
        $user = $request->get("user");
        $userId = $user["id"];
        $userRoleId = $user["role_id"];

        $instanceStep->load([
            "workflowStep",
            "workflowInstance.workflow.documentTypeWorkflow",
        ]);

        $document_type_id =
            $instanceStep->workflowInstance->workflow->documentTypeWorkflow
                ->document_type_id;

        // Vérifie si l'utilisateur peut tout voir
        $canViewAllAttachment = $this->userCanAccessDocument(
            $userId,
            $userRoleId,
            (int) $document_type_id
        );

        $stepActionsResult = [];

        $instanceStep = WorkflowInstanceStep::with([
            "assignments",
            "workflowStep.workflowActionSteps.workflowAction.workflowActionType",
            "workflowStep.workflowActionSteps.transition",
            "workflowStep.workflow",
        ])->findOrFail($instanceStep->id);


        $userAssignment = $instanceStep->assignments
    ->first(function ($assignment) use ($userId, $userRoleId) {

        if ($assignment->user_id) {
            return $assignment->user_id == $userId;
        }

        return $assignment->role_id == $userRoleId;
    });

$alreadyValidated = $userAssignment && $userAssignment->decision === 'APPROVED';


        // throw new Exception(json_encode($alreadyValidated), 1);


        
    

        // =====================================
        // ROLE IDS UNIFIÉS (STATIC + DYNAMIC)
        // =====================================
        $roleIds = $instanceStep->assignments
            ->pluck("role_id")
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $stepSignatures = [];

        // =====================================
        // ACTION STEPS
        // =====================================
        foreach (
            $instanceStep->workflowStep->workflowActionSteps
            as $actionStep
        ) {

         $signatureType = SignatureType::where('code', $actionStep->transaction_type_code)->first();

    $hasSignature = false;

    if ($signatureType) {

        $hasSignature = Signature::where('workflow_instance_step_id', $instanceStep->id)
            ->where('signature_type_id', $signatureType->id)
            ->exists();

        $stepSignatures[] = ["signature_type" => $actionStep->transaction_type_code , "hasSignature" =>  $hasSignature ];
    }

            $stepActionsResult[] = [
                "workflow_action_step_id" => $actionStep->id,
                "permission_required" => $actionStep->permission_required,
                "transaction_type_code" => $actionStep->transaction_type_code,
                "workflow_action_type" => $actionStep->workflowAction->workflowActionType->code,

                // ✔ UNIFIÉ (STATIC + DYNAMIC)
                "role_ids" => $roleIds,

                "transition_type" => $actionStep->transition->type ?? null,
                "workflow_action_name" => $actionStep->workflowAction->name,
                "workflow_action_step_message" => $actionStep->action_step_message,
                "workflow_action_label" => $actionStep->workflowAction->action_label,
                "already_signed" => $hasSignature,
                "can_execute" => !$hasSignature,
            ];
        }

        $result = [
            "alreadyValidated" => $alreadyValidated,
            "global" => [
                "can_view_all_attachment" => $canViewAllAttachment,
            ],
            "steps" => !$alreadyValidated ? $stepActionsResult : [],
            "stepSignatures" => $stepSignatures 
        ];

        return response()->json([
            "success" => true,
            "data" => $result,
        ]);
    }

    private function userCanAccessDocument(
        int $userId,
        int $userRoleId,
        int $documentTypeId
    ): bool {
        // $document = $this->getDocument($documentId);

        // // throw new Exception(json_encode($document['document_type_id']), 1);

        // if (!$document) {
        //     return false;
        // }
        // 0️⃣ Super Admin
        // if ($this->isSuperAdmin($userRoleId)) {
        //     return true;
        // }

        // 1️⃣ Permission globale sur le type
        if (
            $this->hasViewAllPermission(
                $userId,
                "view_all_attachment",
                "document_type",
                $documentTypeId
            )
        ) {
            return true;
        }

        // // 1️⃣ Permission departement sur le type
        // if ($this->hasViewAllPermission($userId , "view_department" , "document_type" , $document["document_type_id"] )) {
        //     return true;
        // }

        // // 2️⃣ Créateur
        // if ($this->isAuthor($userId, $documentId)) {
        //     return true;
        // }

        // // 3️⃣ Demandeur
        // if ($this->isRequester($userId, $documentId)) {
        //     return true;
        // }

        // // 4️⃣ Validateur
        // if ($this->isValidator($userRoleId, $documentId)) {
        //     return true;
        // }

        return false;
    }

    private function hasViewAllPermission(
        int $userId,
        string $action,
        string $resourceType,
        string $resourceId,
        $folderId = null
    ) {
        //$documentTypeId = 8;// $this->getDocumentType($documentId);

        $url = config("services.user_service.base_url") . "/permissions/check";
        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])->get($url, [
            "userId" => $userId,
            "resourceType" => $resourceType,
            "resourceId" => $resourceId,
            "action" => $action,
            "folderId" => $folderId,
        ]);

        if (!$response->successful()) {
            throw new Exception(
                json_encode([
                    "error" =>
                        "Erreur lors de la recuperation de la permission",
                    "url" => $url,
                    "userId" => $userId,
                    "status" => $response->body(),
                ]),
                1
            );

            return response()->json(
                [
                    "error" =>
                        "Erreur lors de la recuperation de la permission",
                    "url" => $url,
                    "status" => $response->status(),
                    "body" => $response->body(),
                ],
                $response->status()
            );
        }

        $permissionData = $response->json();

        return $permissionData["allowed"];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowActionStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowActionStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowActionStep $workflowActionStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowActionStepRequest  $request
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateWorkflowActionStepRequest $request,
        WorkflowActionStep $workflowActionStep
    ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowActionStep  $workflowActionStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowActionStep $workflowActionStep)
    {
        //
    }
}
