<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepRole;
use App\Models\WorkflowCondition;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Facades\Http;
use App\Services\ResolveDepartmentValidator;
use App\Http\Requests\StoreWorkflowInstanceRequest;
use App\Http\Requests\UpdateWorkflowInstanceRequest;
use App\Models\DocumentTypeWorkflow;
use App\Models\Signature;
use App\Models\WorkflowInstanceStepAssignment;
use App\Models\WorkflowInstanceStepRoleDynamic;
use App\Models\WorkflowStatusHistory;
use App\Models\WorkflowStatusLabel;
use App\Models\WorkflowStep;
use App\Notifications\StepReminderNotification;
use App\Services\Workflow\Event\WorkflowEventEngine;
use App\Services\Workflow\WorkflowDynamicResolverService;
use App\Services\Workflow\WorkflowInstanceResolverService;
use App\Services\WorkflowInstanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WorkflowInstanceController extends Controller
{
    use ResolveDepartmentValidator;

    protected WorkflowInstanceService $workflowInstanceService;
    protected WorkflowInstanceResolverService $resolver;

    public function __construct(
        WorkflowInstanceService $workflowInstanceService,
        WorkflowInstanceResolverService $workflowInstanceResolverService
    ) {
        $this->workflowInstanceService = $workflowInstanceService;
        $this->resolver = $workflowInstanceResolverService;
    }

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
     * Récupère l'étape en cours pour un document
     */
    public function getCurrentStepOfDocument(Request $request, $documentId)
    {
        // Exemple : récupère l'étape avec status "en cours"
        $currentInstanceStep = WorkflowInstanceStep::with(["workflowInstance"])
            ->whereHas("workflowInstance", function ($query) use ($documentId) {
                $query->where("document_id", $documentId);
            })
            ->where("status", "PENDING") // ou 'in_progress' selon ton modèle
            ->first();

        if (!$currentInstanceStep) {
            return response()->json(
                [
                    "success" => false,
                    "data" => null,
                    "message" =>
                        "Aucune étape en cours trouvée pour ce document.",
                ]
                //   404
            );
        }

        return response()->json([
            "success" => true,
            "data" => $currentInstanceStep,
        ]);
    }

    // public function getCurrentStep(
    //     WorkflowInstance $instance
    // ): ?WorkflowInstanceStep {
    //     return $instance
    //         ->instance_steps()
    //         ->with("workflowStep")
    //         ->where("status", "PENDING")
    //         ->orderBy("position", "asc")
    //         ->first();
    // }

    public function getCurrentStepValidators($documentId)
    {
        // 1️⃣ Récupérer l'instance de workflow
        $instance = $this->getCurrentWorkflowInstance($documentId);

        // 2️⃣ Récupérer l'étape en cours
        $currentInstanceStep = $this->resolver->getCurrentStep($instance);

        $workflowStep = $currentInstanceStep->workflowStep;

        if ($workflowStep->assignment_mode == "STATIC") {
            $stepRoles = $workflowStep
                ->stepRoles()
                ->pluck("role_id")
                ->toArray();
        } elseif ($workflowStep->assignment_mode == "DYNAMIC") {
            $stepRoles = $currentInstanceStep
                ->roles()
                ->pluck("role_id")
                ->toArray();
        } else {
            $stepRoles = [];
        }

        //         throw new Exception(json_encode(([
        //     'document_id' => $documentId,
        //     'instance_id' => $instance->id,
        //     'workflow_id' => $instance->workflow_id,
        //     'current_step_id' => $currentInstanceStep->id,
        //     'workflow_step_id' => $workflowStep->id,
        //     'assignment_mode' => $workflowStep->assignment_mode,
        // ])), 1);

        return response()->json([
            "validators" => $stepRoles,
        ]);

        // return $instance
        //     ->instance_steps()
        //     ->with("workflowStep")
        //     ->where("status", "PENDING")
        //     ->orderBy("position", "asc")
        //     ->first();
    }

 

    public function history(
    Request $request,
    WorkflowDynamicResolverService $resolver,
    int $documentId
) {

    $workflowInstance = WorkflowInstance::where("document_id", $documentId)
        ->with([
            "instance_steps" => function ($q) {
                $q->whereHas("workflowStep", function ($q2) {
                    $q2->where("is_archived_step", false);
                });
            },
            "instance_steps.workflowStep",
            "instance_steps.assignments",
        ])
        ->firstOrFail();

    /**
     * ===========================================
     * RÉCUPERATION DES ROLE IDS (via assignments)
     * ===========================================
     */
    $roleIds = $workflowInstance->instance_steps
        ->flatMap(fn ($step) => $step->assignments->pluck("role_id"))
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    $roles = [];

    if (!empty($roleIds)) {
        $responseRoles = Http::get(
            config("services.user_service.base_url") . "/roles/getByIds",
            [
                "ids" => implode(",", $roleIds),
            ]
        );

        if ($responseRoles->ok()) {
            $roles = collect($responseRoles->json())->keyBy("id");
        }
    }

    /**
     * ===========================================
     * UTILISATEURS AYANT AGI (via assignments)
     * ===========================================
     */
    $completedUserIds = $workflowInstance->instance_steps
        ->flatMap(fn ($step) => $step->assignments->pluck("user_id"))
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    $users = [];

    if (!empty($completedUserIds)) {
        $responseUsers = Http::get(
            config("services.user_service.base_url") . "/getByIds",
            [
                "ids" => implode(",", $completedUserIds),
            ]
        );

        if ($responseUsers->ok()) {
            $users = collect($responseUsers->json())->keyBy("id");
        }
    }

    /**
     * ===========================================
     * TIMELINE
     * ===========================================
     */
    $instanceSteps = $workflowInstance->instance_steps
        ->map(function ($instanceStep) use ($users, $roles, $resolver) {

            $displayName = null;

            $assignments = $instanceStep->assignments;

            $validatedAssignments = $assignments->where("decision", "APPROVED");

            /**
             * =======================================
             * CAS 1 : ETAPE EXECUTÉE
             * =======================================
             */
            if (
                in_array($instanceStep->status, ["COMPLETE", "REJECTED"]) &&
                $validatedAssignments->isNotEmpty()
            ) {

                // utilisateur(s) ayant validé
                $displayName = $validatedAssignments
                    ->pluck("user_id")
                    ->filter()
                    ->unique()
                    ->map(fn ($id) => $users[$id]["name"] ?? "Utilisateur inconnu")
                    ->implode(" / ");
            }

            /**
             * =======================================
             * CAS 2 : ETAPE EN COURS (DYNAMIC)
             * =======================================
             */
            elseif ($instanceStep->workflowStep->assignment_mode === "DYNAMIC") {

            $agent_user_id = null;

                if ($instanceStep->workflowStep->assignment_rule === "MISSION_EXECUTOR") {
                    

                    $agent_user_id = collect($assignments)->first(fn ($a) => !is_null($a['user_id']))['user_id'] ?? null;


                }
                else{

                }

                $roleIds = $assignments
                    ->pluck("role_id")
                    ->unique()
                    ->values()
                    ->toArray();

               


                $usersByRoles = $resolver->resolveUsersByRoles($roleIds);

                if ($agent_user_id) {
    $usersByRoles = collect($usersByRoles)
        ->map(function ($users) use ($agent_user_id) {
            return collect($users)
                ->filter(fn ($user) => $user['id'] == $agent_user_id)
                ->values()
                ->all();
        })
        ->filter(fn ($users) => count($users) > 0)
        ->toArray();


        //   throw new Exception(json_encode($usersByRoles)); 
}

                



                $displayName = collect($usersByRoles)
                    ->flatten(1)
                    ->pluck("name")
                    ->filter()
                    ->unique()
                    ->implode(" / ");
            }

            /**
             * =======================================
             * CAS 3 : ETAPE STATIQUE
             * =======================================
             */
            else {

                $roleIds = $assignments
                    ->pluck("role_id")
                    ->unique()
                    ->values()
                    ->toArray();

                $usersByRoles = $resolver->resolveUsersByRoles($roleIds);

                $flatUsers = collect($usersByRoles)
                    ->flatten(1)
                    ->pluck("name")
                    ->filter()
                    ->unique()
                    ->values();

                if ($flatUsers->count() === 1) {
                    $displayName = $flatUsers->first();
                } else {
                    $displayName = $roleIds
                        ? ($roles[$roleIds[0]]["name"] ?? "Rôle inconnu")
                        : "Non assigné";
                }
            }

            return [
                "id" => $instanceStep->id,
                "workflow_step_id" => $instanceStep->workflow_step_id,
                "position" => $instanceStep->workflowStep->position,
                "validator" => $displayName,
                "status" => $instanceStep->status,
                "comment" => $instanceStep->comment,
                "acted_at" => $instanceStep->executed_at,
                "is_end" => $instanceStep->workflowStep->is_archived_step,

                // NOUVEAU MODELE
                "role_ids" => $assignments->pluck("role_id")->unique()->values()->toArray(),
                "user_ids" => $assignments->pluck("user_id")->unique()->values()->toArray(),
            ];
        })
        ->sortBy("position")
        ->values()
        ->toArray();

    return response()->json([
        "document_id" => $documentId,
        "workflow_status" => $workflowInstance->status,
        "steps" => $instanceSteps,
    ]);
}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        /*
        DB::transaction(function() use ($step, $userId, $newStatus) {
            // 1. Récupérer l'ancien statut
            $oldStatus = $step->status;
        
            // 2. Mettre à jour l'étape
            $step->status = $newStatus;
            $step->save();
        
            // 3. Ajouter un historique
            DB::table('workflow_instance_steps_history')->insert([
                'workflow_instance_step_id' => $step->id,
                'changed_by' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });*/
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowInstanceRequest  $request
     * @return \Illuminate\Http\Response
     */

    public function store(
        StoreWorkflowInstanceRequest $request,
        WorkflowDynamicResolverService $resolver
    ) {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $userConnected = $validated["created_by"];

            $STATUS_NOT_STARTED = "NOT_STARTED";
            $STATUS_PENDING = "PENDING";
            $STATUS_COMPLETE = "COMPLETE";

            $departmentId = $validated["department_id"];

            // 1️⃣ Créer l'instance de workflow
            $workflowInstance = WorkflowInstance::create([
                "workflow_id" => $validated["workflow_id"],
                "document_id" => $validated["document_id"],
                "status" => $STATUS_PENDING,
            ]);

            // 2️⃣ Créer toutes les étapes de l'instance
            $instanceSteps = [];

            // return

            $documentData = $this->getDocumentData($workflowInstance, $request);

            // throw new Exception(gettype($validated["steps"]));

            $validated["steps"];

            //  throw new Exception(json_encode(collect($validated["steps"])->pluck('id')  , JSON_PRETTY_PRINT), 1);

            $strict = [];

            foreach ($validated["steps"] as $index => $step) {
                //  throw new Exception(($step["id"]), 1);

                if ($step["id"] == 230) {
                    //    throw new Exception(json_encode($step), 1);
                }

                $strict[] = [
                    "value" => $step["assignment_mode"],
                    "is_stict" => $step["assignment_mode"] === "DYNAMIC",
                ];

                if ($step["assignment_mode"] === "STATIC") {
                    //  throw new Exception(json_encode($step), 1);
                }
                // return $step;
                // Déterminer les rôles à partir de assignationMode
                $stepRoles = [];
                if ($step["assignment_mode"] === "STATIC") {
                    $stepRoles = WorkflowStepRole::where(
                        "workflow_step_id",
                        $step["id"]
                    )
                        ->pluck("role_id")
                        ->toArray();
                } elseif ($step["assignment_mode"] === "OWNER") {
                    //   return
                    $stepRoles = [$userConnected["role_ids"][0]];
                } elseif ($step["assignment_mode"] === "DYNAMIC") {
                    //  return "okay";

                    if ($step["assignment_rule"] === "DEPARTMENT_SUPERVISOR") {
                        //il faut ue fonction qui prends en parametre le role et retourne le departement

                        //return [$userConnected['id']];
                        $departmentId = $this->getDepartmentByUsers([
                            $userConnected["id"],
                        ])["department_id"];
                    } elseif ($step["assignment_rule"] === "DIRECT_MANAGER") {
                        $actor = $resolver->resolveActor($documentData); //  $documentData[$documentData["document_type"]["slug"]]["actor_details"];
                        // $validatorRole = $this->getRoleValidator($departmentId);
                        // throw new Exception(json_encode($step["assignment_rule"]), 1);
                        // throw new Exception(json_encode($actor["department_data"]['manager']['employee']['user']['role_ids']), 1);
                        if (!isset($actor["department_data"]["manager"])) {
                            $stepRoles = [];
                        } else {
                            $stepRoles =
                                $actor["department_data"]["manager"][
                                    "employee"
                                ]["user"]["role_ids"];
                        }
                        // throw new Exception(json_encode($stepRoles), 1);
                    } elseif (
                        $step["assignment_rule"] === "HEAD_OF_DEPARTMENT"
                    ) {
                        $dynamicUser = $resolver->resolveHeadStepRole(
                            $step,
                            $documentData
                        );

                        $user = $resolver->resolveUser($dynamicUser["user_id"]);

                        // throw new Exception(json_encode($user), 1);


                        $stepRoles = $user["role_ids"];


                        // $validatorRole = $this->getRoleValidator($departmentId);
                        // $stepRoles = $documentData[$documentData["document_type"]["slug"]]["actor_details"]["employee"]["manager"]["user"]["role_ids"];
                        // throw new Exception(json_encode($validatorRole), 1);
                    } elseif ($step["assignment_rule"] === "MISSION_EXECUTOR") {
                        $missionExecutor = $resolver->resolveActor(
                            $documentData
                        );

                        $agent_user_id = $documentData[$documentData["document_type"]["slug"]][
                            "actor_details"
                        ]["id"];

                        // throw new Exception(json_encode($documentData[$documentData["document_type"]["slug"]]["actor_details"]), 1);

                        $stepRoles = $missionExecutor["role_ids"];

                        // throw new Exception(json_encode($agent_user_id), 1);

                        // $validatorRole = $this->getRoleValidator($departmentId);
                        // $stepRoles = $documentData[$documentData["document_type"]["slug"]]["actor_details"]["employee"]["manager"]["user"]["role_ids"];
                        // throw new Exception(json_encode($validatorRole), 1);
                    } elseif ($step["assignment_rule"] === "SIGNATORY") {
                        $dynamicUsers = $resolver->resolveHeadStepRole(
                            $step,
                            $documentData
                        );

                        $CURRENT_SIGNATORY_ROLE_ID = $documentData['user']['roles']; ////le signatiare qui soumet le PT

                        // $DG_ROLE_ID =
                            // collect($dynamicUsers)
                            //     ->pluck("roles")
                            //     ->flatten(1)
                            //     ->firstWhere("name", "Directeur General")[
                            //     "id"
                            // ] ?? null;

                          $dynamicUsers = array_filter($dynamicUsers, function ($dynamicUser) use ($CURRENT_SIGNATORY_ROLE_ID) {
    foreach ($dynamicUser['roles'] as $role) {
        if ( in_array($role['id'],$CURRENT_SIGNATORY_ROLE_ID)) {
            return false; // exclure cet utilisateur
        }
    }

    return true;
});
                        // throw new Exception(json_encode($dynamicUsers), 1);
                        // throw new Exception(json_encode($CURRENT_SIGNATORY_ROLE_ID), 1);



                        // $dynamicUsers = collect($dynamicUsers)
                        //     ->reject(function ($user) use ($DG_ROLE_ID) {
                        //         return collect($user["roles"])
                        //             ->pluck("id")
                        //             ->contains($DG_ROLE_ID);
                        //     })
                        //     ->values();

                        // throw new Exception(json_encode($dynamicUsers), 1);

                        $stepRoles = collect($dynamicUsers)
                            ->pluck("roles")
                            ->flatten(1)
                            ->pluck("id")
                            ->unique()
                            ->values()
                            ->toArray();

                        // $userIds = collect($dynamicUsers)
                        //     ->pluck("id")
                        //     ->toArray();

                        // $users = $resolver->resolveUsers($userIds);

                        // $stepRoles = collect($users)
                        //     ->pluck("roles")
                        //     ->flatten(1)
                        //     ->pluck("id")
                        //     ->unique()
                        //     ->values()
                        //     ->toArray();

                        // throw new Exception(json_encode($stepRoles), 1);
                    } else {
                        throw new Exception(
                            "Aucune regle d'assigantion pour {$step["assignment_rule"]}",
                            1
                        );
                    }

                    // if ($departmentId) {
                    //     //throw new Exception(json_encode('$stepRoles'), 1);

                    //     // récupération dynamique du rôle selon le département
                    //     $validatorRole = $this->getRoleValidator($departmentId);
                    //     if ($validatorRole) {
                    //         $stepRoles = [$validatorRole["id"]];
                    //     }
                    // } else {
                    //     $stepRoles = [];
                    // }

                    // if ($departmentId) {
                    //throw new Exception(json_encode('$stepRoles'), 1);

                    // récupération dynamique du rôle selon le département
                    // $validatorRole = $this->getRoleValidator($departmentId);
                    //     if ($validatorRole) {
                    //         $stepRoles = [$validatorRole["id"]];

                    // } else {
                    //     $stepRoles = [];
                    // }
                } else {
                    throw new Exception("Aucun mode de traitement", 1);
                }

               

                // if ($step["assignment_mode"] === "DYNAMIC") {
                //     // =====================================
                //     // UNE SEULE ETAPE
                //     // =====================================

                //     $stepInstance = WorkflowInstanceStep::create([
                //         "workflow_instance_id" => $workflowInstance->id,
                //         "workflow_step_id" => $step["id"],
                //         "role_id" => null, // ou rôle principal
                //         "user_id" => null,
                //         "status" =>
                //             $index === 0
                //                 ? $STATUS_PENDING
                //                 : $STATUS_NOT_STARTED,
                //         "due_date" => now()->addHours(
                //             $step["delay_hours"] ?? 24
                //         ),
                //         "position" => $step["position"],
                //     ]);

                //     $stepInstance->load("workflowStep.workflowStatusLabel");

                //     $instanceSteps[$step["id"]] = $stepInstance;

                //     // =====================================
                //     // STOCKAGE DES ROLES DYNAMIQUES
                //     // =====================================

                //     foreach ($stepRoles as $roleId) {
                //         WorkflowInstanceStepRoleDynamic::create([
                //             "workflow_instance_step_id" => $stepInstance->id,
                //             "role_id" => $roleId,
                //         ]);
                //     }
                // } else {
                //     // =====================================
                //     // MODE CLASSIQUE
                //     // =====================================

                //     foreach ($stepRoles as $roleId) {
                //         $initialStatus = $STATUS_NOT_STARTED;
                //         $stepUserId = null;

                //         if (
                //             $index === 0 &&
                //             $roleId == $userConnected["role_id"]
                //         ) {
                //             $initialStatus = $STATUS_COMPLETE;
                //             $stepUserId = $userConnected["id"];
                //         }

                //         $stepInstance = WorkflowInstanceStep::create([
                //             "workflow_instance_id" => $workflowInstance->id,
                //             "workflow_step_id" => $step["id"],
                //             "role_id" => $roleId,
                //             "user_id" => $stepUserId,
                //             "status" => $initialStatus,
                //             "due_date" => now()->addHours(
                //                 $step["delay_hours"] ?? 24
                //             ),
                //             "executed_at" =>
                //                 $initialStatus === $STATUS_COMPLETE
                //                     ? now()
                //                     : null,
                //             "position" => $step["position"],
                //         ]);

                //         $stepInstance->load("workflowStep.workflowStatusLabel");

                //         $instanceSteps[$step["id"]][$roleId] = $stepInstance;
                //     }
                // }

                // if ($step["assignment_mode"] === "DYNAMIC") {
$initialStatus = $index === 0
    ? $STATUS_PENDING
    : $STATUS_NOT_STARTED;

// =====================================
// INSTANCE STEP
// =====================================
$stepInstance = WorkflowInstanceStep::create([
    "workflow_instance_id" => $workflowInstance->id,
    "workflow_step_id" => $step["id"],
    "status" => $initialStatus,
    "due_date" => now()->addHours($step["delay_hours"] ?? 24),
    "position" => $step["position"],
]);

$stepInstance->load("workflowStep.workflowStatusLabel");

$instanceSteps[$step["id"]] = $stepInstance;

// =====================================
// ASSIGNMENTS
// =====================================
$assignmentIds = [];

foreach ($stepRoles as $roleId) {

    $assignment = WorkflowInstanceStepAssignment::create([
        "instance_step_id" => $stepInstance->id,
        "user_id" => $agent_user_id ?? null,
        "role_id" => $roleId,
        "source_type" => $step["assignment_mode"],
        "decision" => "PENDING",
        "can_validate" => true,
        "can_reject" => true,
    ]);

    $assignmentIds[] = $assignment;

    // =====================================
    // AUTO VALIDATION PREMIERE ETAPE
    // =====================================
    if (
        $index === 0 &&
        $roleId === $userConnected["role_id"]
    ) {
        $assignment->update([
            "user_id" => $userConnected["id"],
            "decision" => "APPROVED",
            "validated_at" => now(),
        ]);
    }
}

$hasApproved = WorkflowInstanceStepAssignment::where('instance_step_id', $stepInstance->id)
    ->where('decision', 'APPROVED')
    ->exists();

if ($index === 0 && $hasApproved) {
    $stepInstance->status = $STATUS_COMPLETE;
    $stepInstance->executed_at = now();
    $stepInstance->save();
}
// }


            }

            // $documentData = $this->getDocumentData($workflowInstance, $request);

            //  throw new Exception(json_encode($strict), 1);

            $firstStep = $this->getFirstStepInstance($workflowInstance);

            //  throw new Exception(json_encode($firstStep), 1);

            $stepData = $this->getNextStep(
                $workflowInstance,
                $firstStep,
                $documentData
            );

            if (!$stepData) {
                // throw new Exception(json_encode('$stepData'), 1);
            }

            $nextStep = $stepData["next_step"];
            if ($nextStep) {
                $nextStep->update(["status" => "PENDING"]);
                $workflowInstance->update([
                    "workflow_status_label_id" =>
                        $stepInstance->workflowStep->workflowStatusLabel->id ??
                        "NO STATUS",
                ]);
                $this->workflowInstanceService->notifyNextValidator(
                    $nextStep,
                    $request,
                    $departmentId,
                    $stepRoles
                );
            }

            //    throw new Exception(json_encode($stepsToNotify), 1);

            DB::commit();

            return response()->json(
                $workflowInstance->load(["instance_steps"]),
                // $workflowInstance->load(["instance_steps" ,"activeInstanceStep.workflowStep.workflowStatusLabel"]),
                201
            );

            /* return response()->json(["success"=>false,"data"=>["workfowInstance"=>
             $workflowInstance->load('instance_steps')]], 201);*/
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function getFirstStepInstance(WorkflowInstance $workflowInstance)
    {
        // Récupère toutes les étapes de l'instance
        $steps = $workflowInstance->instance_steps;

        // Si tu veux juste la première étape selon la position
        $firstStep = $steps->sortBy("position")->first();

        return $firstStep;
    }

    public function testNotify(
        Request $request,
        WorkflowInstanceStep $workflowInstanceStep,
        $departmentId
    ) {
        // ✅ Voir ce que contient l'étape
        //if ($request->has('debug')) {
        //  return response()->json($workflowInstanceStep);
        //}

        // ✅ Appeler ton service
        return $result = $this->workflowInstanceService->notifyNextValidator(
            $workflowInstanceStep,
            $request,
            $departmentId
        );

        if ($result && $result["success"]) {
            return $result;
        } else {
            return response()->json([
                "success" => false,
                //  'data' => $result
            ]);
        }
    }

    public function testRemind()
    {
        // Récupère les étapes PENDING dont la date limite est dépassée
        $instanceSteps = WorkflowInstanceStep::where("status", "PENDING") //with('roles.user') // ou avec workflow_step_roles si statique
            ->where("due_date", "<", Carbon::now())
            ->get();

        foreach ($instanceSteps as $instanceStep) {
            $usersToNotify = collect();

            if ($instanceStep->user_id) {
                $userIds = [$instanceStep->user_id];
                continue;
            }

            if ($instanceStep->workflowStep->assignment_mode === "STATIC") {
                // étape statique : on a les role_ids dans workflow_step_roles
                $roleIds = $instanceStep->stepRoles->pluck("role_id"); // IDs des rôles depuis workflow
            } elseif (
                $instanceStep->workflowStep->assignment_mode === "DYNAMIC"
            ) {
                // étape dynamique
                if ($instanceStep->user_id) {
                    $userIds = [$instanceStep->user_id];
                } elseif ($instanceStep->role_id) {
                    //$roleIds = [$instanceStep->role_id];
                    // étape dynamique : récupérer les rôles assignés à cette instance d'étape
                    $roleIds = $instanceStep->roles()->pluck("role_id");
                }
            }

            //    return $roleIds;
            //   $users = collect();

            $workflowInstance = $instanceStep->workflowInstance;
            //  $documentId = $workflowInstance->document_id;
            //  $stepName = $stepInstance->workflowStep->name;

            $workflowId = $workflowInstance->workflow_id;

            // Récupérer le type de document associé au workflow
            $documentTypeWorkflow = DocumentTypeWorkflow::where(
                "workflow_id",
                $workflowId
            )->first();

            $documentTypeId = $documentTypeWorkflow
                ? $documentTypeWorkflow->document_type_id
                : null; // null si pas trouvé

            $payload = [
                "instance_step_id" => $instanceStep->id,
                "workflow_instance_id" => $instanceStep->workflow_instance_id,
                "workflow_step_name" => $instanceStep->workflowStep->name,
                "role_ids" => $roleIds->toArray(), // pour les étapes statiques ou dynamiques
                "user_id" => $instanceStep->user_id, // pour les assignations directes
                "notification_channel" =>
                    $instanceStep->workflowStep->notification_channel ?? "mail",
                "document_type_id" => $documentTypeId,
            ];

            // Appel microservice pour récupérer les users par role
            if ($roleIds->isNotEmpty()) {
                return $response = Http::acceptJson()->post(
                    config("services.user_service.base_url") .
                        "/send-step-reminder",
                    $payload
                );
            }

            // Incrémente le compteur de relances
            $instanceStep->increment("reminder_count");
        }

        //$this->info('Relances envoyées aux validateurs en retard.');
    }

    public function getDocumentData(
        WorkflowInstance $instance,
        Request $request
    ) {
        $traceId = (string) Str::uuid();

        $user = $request->get("user");

        try {
            Log::info("Workflow: récupération document START", [
                "trace_id" => $traceId,
                "workflow_instance_id" => $instance->id,
                "document_id" => $instance->document_id,
                "user_id" => $user["id"] ?? null,
            ]);

            // 🔥 APPEL SERVICE DOCUMENT
            $response = Http::withToken($request->bearerToken())
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 200)
                ->get(
                    config("services.document_service.base_url") .
                        "/{$instance->document_id}"
                );

            //  throw new Exception(json_encode('$instance'));

            if (!$response->successful()) {
                Log::error("Workflow: échec récupération document", [
                    "trace_id" => $traceId,
                    "status" => $response->status(),
                    "response_body" => $response->body(),
                    "document_id" => $instance->document_id,
                ]);

                throw new Exception(
                    "Impossible de récupérer le document (service error {$response->status()})"
                );
            }

            //  throw new Exception("Récupération du document ok");

            $documentData = $response->json();

            $documentId = $documentData["id"];

            $documentData["signatures"] = Signature::query()
                ->where("document_id", $documentId)
                ->with("signatureType")
                ->get()
                ->map(
                    fn($s) => [
                        "signature_type_id" => $s->signatureType->id,
                        "type" => $s->signatureType->code ?? "--",
                        "label" => $s->signatureType->name ?? "--",
                        "user_id" => $s->user_id,
                        "signed_at" => $s->signed_at,
                    ]
                )
                ->toArray();

            // 🔥 Roles
            $roles = collect($user["roles"] ?? [])
                ->pluck("id")
                ->toArray();

            // 🔥 Permissions normalisées
            $permissions = collect($user["effective_permissions"] ?? [])
                ->map(
                    fn($perm) => is_array($perm)
                        ? $perm["id"] ?? ($perm["name"] ?? null)
                        : $perm
                )
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // 🔥 Injection user context
            $documentData["user"] = [
                "id" => $user["id"] ?? null,
                "roles" => $roles,
                "permissions" => $permissions,
            ];

            Log::info("Workflow: récupération document SUCCESS", [
                "trace_id" => $traceId,
                "document_id" => $instance->document_id,
            ]);

            return $documentData;
        } catch (Exception $e) {
            // Log::error("Workflow: exception getDocumentData", [
            //     "trace_id" => $traceId,
            //     "workflow_instance_id" => $instance->id,
            //     "document_id" => $instance->document_id,
            //     "message" => $e->getMessage(),
            //     "trace" => $e->getTraceAsString(),
            // ]);

            Log::error(
                "[WORKFLOW_SERVICE] Erreur lors de la récupération du document",
                [
                    "document_id" => $instance->document_id,
                    // "message" => $e->getMessage(),
                    "trace_id" => $traceId,
                    "workflow_instance_id" => $instance->id,
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "trace" => $e->getTraceAsString(),
                ]
            );
            // throw new Exception("Erreur lors de la récupération du document (trace: {$traceId})");
            throw new Exception(
                "Erreur lors de la récupération du document (trace: {$e->getMessage()})"
            );
        }
    }

    //    public function getDocumentData(WorkflowInstance $instance, $request): array
    // {
    //     $user = $request->get("user");

    //     // throw new Exception(json_encode($user), 1);

    //     $response = Http::withToken($request->bearerToken())
    //         ->acceptJson()
    //         ->get(
    //             config("services.document_service.base_url") .
    //                 "/{$instance->document_id}"
    //         );

    //     if (!$response->successful()) {
    //         throw new \Exception(
    //             "Impossible de récupérer le document : " . $response->status()
    //         );
    //     }

    //     $documentData = $response->json();

    //     // 🔥 Roles → IDs uniquement
    //     $roles = collect($user["roles"] ?? [])
    //         ->pluck("id")
    //         ->toArray();

    //     // 🔥 Permissions → normaliser en noms ou IDs, flatten pour éviter doublons imbriqués
    // $permissions = collect($user["effective_permissions"] ?? [])
    //     ->map(fn($perm) => is_array($perm) ? ($perm["id"] ?? $perm["name"]) : $perm) // choisir id ou name
    //     ->flatten()    // déplie tout tableau imbriqué
    //     ->unique()     // supprime doublons
    //     ->values()     // réindexe
    //     ->toArray();   // retourne un vrai tableau PHP

    //     $documentData["user"] = [
    //         "id" => $user["id"] ?? null,
    //         "roles" => $roles,
    //         "permissions" => $permissions,
    //     ];

    //     return $documentData;
    // }

    private function hasPermission(
        int $userId,
        string $action,
        string $resourceType,
        string $resourceId,
        $folderId = null
    ) {
        //$documentTypeId = 8;// $this->getDocumentType($documentId);

        $url = config("services.user_service.base_url") . "/permissions/check";
        $response = Http::withHeaders($this->gatewayHeaders())->get($url, [
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

    public function getCurrentWorkflowInstance($documentId): WorkflowInstance
    {
        return WorkflowInstance::whereDocumentId($documentId)->firstOrFail();
    }

    public function checkIfHasBlocker(Request $request, $documentId)
    {
        DB::beginTransaction();

        try {
            $user = $request->get("user");
            $action = Str::lower($request->get("condition"));

            // 1️⃣ Récupérer l'instance de workflow
            $instance = $this->getCurrentWorkflowInstance($documentId);

            // 2️⃣ Récupérer l'étape en cours
            $currentStep = $this->resolver->getCurrentStep($instance);

            if (!$currentStep) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Aucune étape en cours trouvée.",
                    ],
                    400
                );
            }

            $documentData = $this->getDocumentData($instance, $request);

            //  throw new Exception(json_encode($documentData), 1);

            // 🔹 Vérifier les règles de blocage avant validation
            // return
            $blockingData = $this->checkBlockingRules(
                $instance,
                $currentStep,
                $documentData
            );

            if (!$blockingData["isValid"] /*&& false*/) {
                $step = WorkflowStep::with("attachmentTypes")->find(
                    $currentStep->workflowStep->id
                );

                // Récupérer les IDs des attachment_types requis
                $attachmentTypeRequired = $step->attachmentTypes
                    ->pluck("attachment_type_id")
                    ->toArray();

                $response = Http::withToken(request()->bearerToken())
                    ->acceptJson()
                    ->post(
                        config("services.document_service.base_url") .
                            "/{$documentId}/missing-attachment-types",
                        [
                            "attachment_type_required" => $attachmentTypeRequired,
                        ]
                    );
                return response()->json([
                    "success" => false,
                    "data" => $blockingData["data"],
                    "required_attachments" => $response->successful()
                        ? $response->json()
                        : [],
                ]);
            }

            DB::commit();

            // 4️⃣ Déterminer l’étape suivante via les transitions conditionnelles
            $stepData = $this->getNextStep(
                $instance,
                $currentStep,
                $documentData
                // $action
            );

            // throw new Exception(json_encode($stepData), 1);

            $nextStep = $stepData["next_step"];
            $isDynamic = $stepData["isDynamic"];

            return response()->json([
                "success" => true,
                "message" => "Aucun blocker à cette etape",
                "currentStep" => $currentStep,
                "nextStep" => $nextStep,
                "isDynamicStep" => $isDynamic,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
            return response()->json(
                [
                    "success" => false,
                    "message" => $th->getMessage(),
                ],
                500
            );
        }
    }

    //     protected function resolveWorkflowStatusLabel($currentStep, $instance)
    // {
    //     $step = $currentStep->workflowStep;

    //     //  throw new Exception(json_encode($step), 1);

    //     // 1️⃣ étape de paiement
    //     if ($step->is_payment_step) {

    //         $response = Http::withToken(request()->bearerToken())
    //     ->get(config('services.document_service.base_url')."/".$instance->document_id."/payment-status");

    // $paymentStatus = $response->json()['status'];

    // // if ($paymentStatus === 'PARTIALLY_PAID') {
    // //     $label = WorkflowStatusLabel::where('code','PARTIALLY_PAID')->first();
    // // }

    // // if ($paymentStatus === 'PAID') {
    // //     $label = WorkflowStatusLabel::where('code','PAID')->first();
    // // }

    //     // throw new Exception(WorkflowStatusLabel::where('code', $paymentStatus)->first(), 1);

    //         return WorkflowStatusLabel::where('code', $paymentStatus)->first();
    //     }

    //     // 2️⃣ label configuré sur la step
    //     if ($step->workflowStatusLabel) {
    //         return $step->workflowStatusLabel;
    //     }

    //     return null;
    // }

    public function registerPayment($instance, $currentStep, $request, $user)
    {
        if ($currentStep->workflowStep->is_payment_step) {
            //  throw new Exception(json_encode($request->all()), 1);

            $documentId = $instance->document_id;

            $payload = [
                "paid_amount" => $request->get("paid_amount"), // montant payé
                "is_full_pay" => $request->get("is_full_pay"), // paiement total ou partiel
                "payment_mode" => $request->get("payment_mode"),
                "user_id" => $user["id"], // qui effectue le paiement
            ];

            $response = Http::withToken($request->bearerToken())
                ->acceptJson()
                ->post(
                    config("services.document_service.base_url") .
                        "/{$documentId}/register-payment",
                    $payload
                );

            if ($response->failed()) {
                throw new \Exception(
                    "Impossible d'enregistrer le paiement : " .
                        $response->body()
                );
            }

            return $updatedDocument = $response->json();

            // Mettre à jour le label de l'instance workflow
            $workflowStatusLabel =
                $currentStep->workflowStep->workflowStatusLabel;
            $instance->update([
                "status_label" => $workflowStatusLabel->label ?? "NO STATUS",
            ]);
        }
    }

 

   public function validateStep(
    Request $request,
    WorkflowEventEngine $WorkflowEventEngine,
    $documentId
) {
    DB::beginTransaction();

    try {

        // =====================================
        // CONTEXTE UTILISATEUR
        // =====================================
        $user = $request->get("user");
        $actionStepId = Str::lower($request->get("actionStepId"));

        // =====================================
        // WORKFLOW INSTANCE
        // =====================================
        $instance = WorkflowInstance::whereDocumentId($documentId)->firstOrFail();

        $currentStep = $this->resolver->getCurrentStep($instance);

        if (!$currentStep) {
            return response()->json([
                "success" => false,
                "message" => "Aucune étape en cours trouvée.",
            ], 400);
        }

        $oldStatus = $currentStep->status;
        $historyDataArray = [];

        $documentData = $this->getDocumentData($instance, $request);

        // =====================================
        // BLOCKING RULES
        // =====================================
        $blockingData = $this->checkBlockingRules(
            $instance,
            $currentStep,
            $documentData
        );

        if (!$blockingData["isValid"] && false) {
            return response()->json([
                "success" => false,
                "message" => $blockingData["data"]["message"],
                "currentStep" => $currentStep,
            ]);
        }

        // =====================================
        // VALIDATION VIA ASSIGNMENTS
        // =====================================
        $assignment = WorkflowInstanceStepAssignment::where('instance_step_id', $currentStep->id)
            ->where('user_id', $user["id"])
            ->first();

        if (!$assignment) {
            $assignment = WorkflowInstanceStepAssignment::where('instance_step_id', $currentStep->id)
                ->where('role_id', $user["role_id"])
                ->first();
        }

        if ($assignment) {
            $assignment->update([
                "user_id" => $user["id"],
                "decision" => "APPROVED",
                "validated_at" => now(),
            ]);
        }

        // =====================================
        // RECALCUL STATUS STEP
        // =====================================
        $assignments = WorkflowInstanceStepAssignment::where('instance_step_id', $currentStep->id)->get();

        $hasApproved = $assignments->where('decision', 'APPROVED')->isNotEmpty();
        $hasRejected = $assignments->where('decision', 'REJECTED')->isNotEmpty();

        if ($hasRejected) {
            $currentStep->status = "REJECTED";
        } elseif ($hasApproved) {
            $currentStep->status = "COMPLETE";
            $currentStep->executed_at = now();
        } else {
            $currentStep->status = "PENDING";
        }

        $currentStep->save();

        // =====================================
        // NEXT STEP LOGIC
        // =====================================
        $stepData = $this->getNextStep(
            $instance,
            $currentStep,
            $documentData
        );

        $nextStep = $stepData["next_step"];
        $isDynamic = $stepData["isDynamic"];

        $instanceSteps = [];

        // =====================================
        // DYNAMIC NEXT STEP CREATION
        // =====================================
        if ($isDynamic) {

            $validatorRole = $this->getRoleValidator(
                $request->get("department_id")
            );

            if ($validatorRole) {
                $stepRoles = [$validatorRole["id"]];
            }

            $step = $currentStep->workflowStep;

            $transitions = $step->outgoingTransitions;

            $nextWorkflowStep = $transitions->map(function ($transition) {
                return $transition->toStep;
            })[0];

            $stepInstance = WorkflowInstanceStep::create([
                "workflow_instance_id" => $instance->id,
                "workflow_step_id" => $nextWorkflowStep->id,
                "role_id" => $validatorRole["id"],
                "status" => "PENDING",
                "due_date" => now()->addHours($nextWorkflowStep["delay_hours"] ?? 24),
                "executed_at" => null,
                "position" => $nextWorkflowStep->position,
            ]);

            $instanceSteps[$nextWorkflowStep->id][$validatorRole["id"]] = $stepInstance;

            $nextStep = $stepInstance;

            if ($nextWorkflowStep["assignment_mode"] === "DYNAMIC") {
                WorkflowInstanceStepRoleDynamic::create([
                    "workflow_instance_step_id" => $stepInstance->id,
                    "role_id" => $validatorRole["id"],
                ]);
            }
        }

        // =====================================
        // PAYMENT
        // =====================================
        $result = $this->registerPayment(
            $instance,
            $currentStep,
            $request,
            $user
        );

        $newDoc = $result["document"] ?? null;

        // =====================================
        // WORKFLOW LABEL
        // =====================================
        $label = $this->resolver->resolveWorkflowStatusLabel($instance);

        // =====================================
        // NEXT STEP HANDLING
        // =====================================
        if ($nextStep) {

            if ($nextStep->workflowStep->is_archived_step) {

                $nextStep->update([
                    "status" => "COMPLETE",
                    "executed_at" => now(),
                ]);

                WorkflowInstanceStepAssignment::where('instance_step_id', $nextStep->id)
                    ->update([
                        "decision" => "APPROVED",
                        "validated_at" => now(),
                        "user_id" => $user["id"],
                    ]);

                $instance->update([
                    "status" => "COMPLETE",
                    "workflow_status_label_id" => $label->id ?? null,
                ]);

            } else {

                $nextStep->update([
                    "status" => "PENDING",
                ]);

                $instance->update([
                    "status" => "PENDING",
                    "workflow_status_label_id" => $label->id ?? null,
                ]);

                $this->workflowInstanceService->notifyNextValidator(
                    $nextStep,
                    $request,
                    $request->get("department_id")
                );
            }

        } else {

            $instance->update([
                "status" => "COMPLETE",
                "status_label_id" => $label->id ?? null,
            ]);
        }

        // =====================================
        // HISTORY
        // =====================================
        $historyDataArray[] = [
            "model_id" => $currentStep->id,
            "model_type" => get_class($currentStep),
            "changed_by" => $user["id"],
            "old_status" => $oldStatus,
            "new_status" => $currentStep->status,
            "comment" => $request->get("comment"),
        ];

        $historyDataArray = array_map(
            fn($data) => array_filter($data, fn($v) => !is_null($v)),
            $historyDataArray
        );

        foreach ($historyDataArray as $historyData) {
            WorkflowStatusHistory::create($historyData);
        }

        // DB::commit();

        // throw new Exception(json_encode($historyDataArray), 1);
        

        // =====================================
        // MINI ENGINE
        // =====================================
        $WorkflowEventEngine->handle(
            $documentId,
            $instance,
            $currentStep,
            $actionStepId
        );

        return response()->json([
            "success" => true,
            "message" => "Paiement finalisé avec succès",
            "currentStep" => $currentStep,
            "nextStep" => $nextStep,
            "instance" => $instance,
        ]);

    } catch (\Throwable $th) {
        DB::rollBack();

        return response()->json([
            "success" => false,
            "message" => $th->getMessage(),
        ], 500);
    }
}

    public function rejectStep(Request $request, $documentId)
    {
        DB::beginTransaction();

        try {
            $user = $request->get("user");
            $action = Str::lower($request->get("condition"));

            // 1️⃣ Récupérer l'instance de workflow
            $instance = WorkflowInstance::whereDocumentId(
                $documentId
            )->firstOrFail();

            // 2️⃣ Récupérer l'étape en cours
            $currentStep = $this->resolver->getCurrentStep($instance);
            $oldStatus = $currentStep->status;
            $histories = [];
            $historyDataArray = [];

            if (!$currentStep) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Aucune étape en cours trouvée.",
                    ],
                    400
                );
            }

            /*$documentData = $this->getDocumentData($instance, $request);

            // 🔹 Vérifier les règles de blocage avant validation
            $blockingData = $this->checkBlockingRules(
                $instance,
                $currentStep,
                $documentData
            );

            if (!$blockingData["isValid"] && false) {
                return response()->json([
                    "success" => false,
                    "message" => $blockingData["data"]["message"],
                    "currentStep" => $currentStep,
                    //"nextStep" => $nextStep,
                ]);
            }
            */

            // 3️⃣ Marquer l’étape comme validée
            $currentStep->update([
                "status" => "REJECT",
                "user_id" => $user["id"],
                "executed_at" => now(),
                "validated_at" => now(),
            ]);

            $instance->update([
                "status" => "REJECT",
            ]);

            // 4️⃣ Déterminer l’étape suivante via les transitions conditionnelles
            /*$stepData = $this->getNextStep(
                $instance,
                $currentStep,
                $documentData,
                $action
            );

            // 2️⃣ Créer toutes les étapes de l'instance
            $instanceSteps = [];

            $nextStep = $stepData["next_step"];
            $isDynamic = $stepData["isDynamic"];

            if ($isDynamic) {
                ////ici on va creer l'etape suivante dynamique

                $validatorRole = $this->getRoleValidator(
                    $request->get("department_id")
                );
                if ($validatorRole) {
                    $stepRoles = [$validatorRole["id"]];
                }

                $step = $currentStep->workflowStep;

                // 2️⃣ Récupérer les transitions sortantes depuis ce Step
                $transitions = $step->outgoingTransitions; // relation à définir

                // 3️⃣ Parcourir les steps suivants
                $nextWorkflowStep = $transitions->map(function ($transition) {
                    return $transition->toStep; // relation à définir
                })[0];

                $stepInstance = WorkflowInstanceStep::create([
                    "workflow_instance_id" => $instance->id,
                    "workflow_step_id" => $nextWorkflowStep->id,
                    "role_id" => $validatorRole["id"],
                    "status" => "PENDING",
                    "due_date" => now()->addHours(
                        $nextWorkflowStep["delay_hours"] ?? 24
                    ), // ou delay_days
                    "executed_at" => null,
                    "position" => $nextWorkflowStep->position,
                ]);

                $instanceSteps[$nextWorkflowStep->id][
                    $validatorRole["id"]
                ] = $stepInstance;

                $nextStep = $stepInstance;
                // 3️⃣ Créer l'entrée WorkflowInstanceStepRole pour les rôles dynamiques
                if ($nextWorkflowStep["assignment_mode"] === "DYNAMIC") {
                    WorkflowInstanceStepRoleDynamic::create([
                        "workflow_instance_step_id" => $stepInstance->id,
                        "role_id" => $validatorRole["id"],
                    ]);
                }
            }
            

            if ($nextStep) {
                ////il y'a encore une autre etape

                //  return $nextStep;

                //verifions si la prchaine
                // Activer la prochaine étape

                if ($nextStep->workflowStep->is_archived_step) {
                    $nextStep->update([
                        "status" => "COMPLETE",
                        "user_id" => $user["id"],
                        "executed_at" => now(),
                        "validated_at" => now(),
                    ]);

                    // Mettre à jour l'instance comme "toujours en cours"
                    $instance->update([
                        "status" => "COMPLETE",
                    ]);

                    $historyDataArray[] = [
                        "model_id" => $currentStep->id,
                        "model_type" => get_class($currentStep),
                        "changed_by" => $user["id"],
                        "old_status" => $oldStatus,
                        "new_status" =>
                            $currentStep->status == "COMPLETE"
                                ? "COMPLETED"
                                : $currentStep->status,
                        "comment" => $request->get("comment"),
                    ];
                } else {
                    $nextStep->update([
                        "status" => "PENDING",
                    ]);

                    // Mettre à jour l'instance comme "toujours en cours"
                    $instance->update([
                        "status" => "PENDING",
                    ]);

                    $this->workflowInstanceService->notifyNextValidator(
                        $nextStep,
                        $request,
                        $request->get("department_id")
                    );
                }

                //$newStatus = "PENDING";
            } else {
                // Pas d’étape suivante → Workflow terminé
                $instance->update([
                    "status" => "COMPLETE",
                ]);

                //$newStatus = "COMPLETE";
            }
            */

            // 🔹 Historisation

            /* $historyData = [
                "model_id" => $currentStep->id,
                "model_type" => get_class($currentStep),
                "changed_by" => $user["id"],
                "old_status" => $oldStatus,
                "new_status" =>
                    $currentStep->status == "COMPLETE"
                        ? "COMPLETED"
                        : $currentStep->status,
                "comment" => $request->get("comment"),
            ];*/

            $historyDataArray[] = [
                "model_id" => $currentStep->id,
                "model_type" => get_class($currentStep),
                "changed_by" => $user["id"],
                "old_status" => $oldStatus,
                "new_status" =>
                    $currentStep->status == "COMPLETE"
                        ? "COMPLETED"
                        : ($currentStep->status == "REJECT"
                            ? "REJECTED"
                            : $currentStep->status),
                "comment" => $request->get("comment"),
            ];

            // Supprimer les clés avec valeur null
            //$historyData = array_filter($historyData, fn($v) => !is_null($v));
            //$history = WorkflowStatusHistory::create($historyData);

            // Supprimer les clés nulles pour chaque entrée
            $historyDataArray = array_map(
                fn($data) => array_filter($data, fn($v) => !is_null($v)),
                $historyDataArray
            );

            // Boucler pour créer les historiques
            foreach ($historyDataArray as $historyData) {
                WorkflowStatusHistory::create($historyData);
            }

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "Étape rejetée avec succès",
                "currentStep" => $currentStep,
                "nextStep" => null,
                // "history" => $history,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(
                [
                    "success" => false,
                    "message" => $th->getMessage(),
                ],
                500
            );
        }
    }

    protected function checkBlockingRules(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        array $documentData
    ) {
        //: void
        $blockingRules = WorkflowCondition::where(
            "workflow_step_id",
            $currentStep->workflow_step_id
        )
            ->where("condition_kind", "BLOCKING")
            ->get();

        foreach ($blockingRules as $rule) {
            //  return

            //  throw new Exception(json_encode($rule), 1);

            $test = $this->evaluateCondition($rule, $documentData);
            if (!$test) {
                //  $test;
                return [
                    "isValid" => false,
                    "data" => [
                        "message" =>
                            "Étape bloquée : Vous devez joindre l'engagement de ce document",
                        "required_type" => $rule->required_type,
                    ],
                ];

                throw new \Exception(
                    "Étape bloquée : Vous devez joindre l'engagement de ce document ({$rule->condition_type})"
                );
            }
        }

        return ["isValid" => true, "data" => ["message" => ""]];
    }

    /**
     * Retourne l'étape suivante selon les transitions et conditions
     */

    protected function getNextStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        array $documentData
    ) {
        $isDynamic = false;
        // Récupère les transitions depuis l'étape courante
        // $transitions = WorkflowTransition::where(
        //     "from_step_id",
        //     $currentStep->workflow_step_id
        // )->get();

        $default_transition = WorkflowTransition::whereDoesntHave(
            "conditions",
            function ($q) {
                $q->where("condition_kind", "PATH");
            }
        )
            ->where("from_step_id", $currentStep->workflow_step_id)
            ->first();

        if (!$default_transition) {
            //    throw new Exception("Aucune transition par défaut définie");
        }
        // throw new Exception($default_transitions, 1);

        $pathtransitions = WorkflowTransition::whereHas("conditions")
            ->with([
                "conditions" => function ($q) {
                    $q->where("condition_kind", "PATH");
                },
            ])
            ->where("from_step_id", $currentStep->workflow_step_id)
            ->get();

        

        foreach ($pathtransitions as $index => $pathtransition) {
            // Récupère les conditions PATH associées à la transition
            // $pathConditions = WorkflowCondition::where(
            //     "workflow_transition_id",
            //     $transition->id
            // )
            //     ->where("condition_kind", "PATH")
            //     ->get();

            $pathConditions = $pathtransition->conditions;

            $groupedConditions = $pathConditions->groupBy("group_id");

            // throw new Exception($transitions, 1);

            // throw new Exception($pathtransition->id, 1);
            // throw new Exception($pathConditions, 1);

            foreach ($groupedConditions as $groupId => $pathConditions) {
                $allSatisfied = true;

                foreach ($pathConditions as $condition) {
                    //return $this->evaluateCondition($condition, $documentData);
                    if (!$this->evaluateCondition($condition, $documentData)) {
                        $allSatisfied = false;
                        break; // une seule condition PATH non remplie → on ignore cette transition
                    }
                }

                //   throw new Exception(json_encode($allSatisfied), 1);

                // ✅ SI UN GROUPE EST VALIDE → ON PREND LA TRANSITION
                if ($allSatisfied) {
                    //   throw new Exception(json_encode($pathtransition), 1);

                    return $this->get_step(
                        $instance,
                        $pathtransition,
                        $isDynamic
                    );
                }

                //     if (!$allSatisfied) {
                //         continue;
                //     }

                // return    $this->get_step($instance , $pathtransition , $isDynamic);

                // throw new Exception($tempWorkflowInstanceStep, 1);
            }
        }

        // throw new Exception("aucune satisfaite", 1);

        // throw new Exception(json_encode($this->get_step($instance, $default_transition, $isDynamic)), 1);

        return $this->get_step($instance, $default_transition, $isDynamic);

        // Aucune transition valide
        return ["isDynamic" => $isDynamic, "next_step" => null];
    }

    function get_step($instance, $transition, $isDynamic)
    {
        $tempWorkflowInstanceStep = WorkflowInstanceStep::where(
            "workflow_instance_id",
            $instance->id
        )
            ->with("workflowStep")
            ->where("workflow_step_id", $transition->to_step_id)
            ->first();

        // throw new Exception($transition, 1);

        if ($transition->to_step_id && !$tempWorkflowInstanceStep) {
            //il y'a un etape dynamique

            $isDynamic = true;

            // throw new Exception("Aucune etape suivante", 1);
        } else {
            // throw new Exception("tempWorkflowInstanceStep", 1);

            return [
                "isDynamic" => $isDynamic,
                "next_step" => $tempWorkflowInstanceStep,
            ];
        }
    }

    /**
     * Évalue une condition sur les données du document
     */
    /**
     * Évalue une condition sur les données du document
     */
    protected function evaluateCondition(
        WorkflowCondition $condition,
        array $data
    ) {
        //: bool
        // Récupérer la valeur du champ (supporte les chemins imbriqués)
        //   return
        $fieldValue = $this->getNestedValue($data, $condition->field ?? "");
        //return $condition->value;

        // throw new Exception(json_encode($condition->field), 1);
        // throw new Exception(json_encode($data), 1);

        // throw new Exception(json_encode($fieldValue), 1);
        //throw new Exception(json_encode(array_map("intval", $condition->required_id)), 1);

        // Si le type de condition est 'exists' (vérifie la présence d'un document ou d'une valeur)
        if ($condition->condition_type === "exists") {
            // Convertir les chaînes en entiers si nécessaire
            $haystack_int = array_map("intval", $condition->required_id);

            if (is_array($fieldValue)) {
                // throw new Exception(json_encode(array_diff($haystack_int, $fieldValue)), 1);

                return !empty($fieldValue) &&
                    //!empty(array_intersect($fieldValue, $haystack_int));
                    empty(array_diff($haystack_int, $fieldValue));
            } else {
                //throw new Exception(json_encode($fieldValue), 1);
                //throw new Exception(json_encode($haystack_int), 1);
                // throw new Exception(json_encode(in_array($fieldValue , $haystack_int) ), 1);

                return in_array($fieldValue, $haystack_int);
            }
        }

        // Si le type de condition est 'userRole' (exemple : vérifier le rôle du soumissionnaire)
        if ($condition->condition_type === "userRole") {
            $userRoles = $data["user"]["roles"] ?? [];

            // sécurité
            if (!is_array($userRoles)) {
                $userRoles = [$userRoles];
            }

            //   throw new Exception(json_encode($userRoles), 1);
            // $conditionValue = json_decode($condition->value, true) ?? [];//$condition->value;
            $conditionValue = $condition->value;

            //   throw new Exception(($condition->value), 1);

            // support multi-values
            if (!is_array($conditionValue)) {
                $conditionValue = [$conditionValue];
            }

            switch ($condition->operator) {
                case "IN":
                    //   throw new Exception(json_encode(count(array_intersect($conditionValue, $userRoles)) > 0), 1);
                    // au moins un rôle correspond
                    return count(array_intersect($conditionValue, $userRoles)) >
                        0;

                case "NOT IN":
                    // aucun rôle ne correspond
                    return count(
                        array_intersect($conditionValue, $userRoles)
                    ) === 0;

                default:
                    // fallback (ancien comportement)
                    return count(array_intersect($conditionValue, $userRoles)) >
                        0;
            }
        }

        if ($condition->condition_type === "userPermission") {
            $userPermissions = $data["user"]["permissions"] ?? [];

            //   throw new Exception(json_encode($userPermissions), 1);

            // sécurité
            if (!is_array($userPermissions)) {
                $userPermissions = [$userPermissions];
            }

            //   throw new Exception(json_encode($userPermissions), 1);

            $conditionValue = $condition->value ?? null;

            // support multi-values
            if (!is_array($conditionValue)) {
                $conditionValue = [$conditionValue];
            }

            //   throw new Exception(json_encode($conditionValue), 1);

            switch ($condition->operator) {
                case "ANY":
                    // au moins une permission correspond
                    return count(
                        array_intersect($conditionValue, $userPermissions)
                    ) > 0;

                case "ALL":
                    // toutes les permissions doivent être présentes
                    return empty(array_diff($conditionValue, $userPermissions));

                default:
                    // fallback = ANY
                    return count(
                        array_intersect($conditionValue, $userPermissions)
                    ) > 0;
            }
        }

        if ($condition->condition_type === "isSubmitter") {
            return isset($data["user"]["id"]) &&
                isset($data["submitted_by"]) &&
                $data["user"]["id"] == $data["submitted_by"];
        }

        if ($condition->condition_type === "isDG") {
            return in_array("DG", $data["user"]["roles"] ?? []);
        }

        if ($condition->condition_type === "isManager") {
            return in_array("MANAGER", $data["user"]["roles"] ?? []);
        }

        // Si le type de condition est 'comparison' ou autre basé sur un opérateur
        if (
            in_array($condition->operator, [
                ">",
                "<",
                "=",
                "!=",
                ">=",
                "<=",
                "IN",
                "NOT IN",
            ])
        ) {
            switch ($condition->operator) {
                case ">":
                    return $fieldValue !== null &&
                        $fieldValue > $condition->value;
                case "<":
                    return $fieldValue !== null &&
                        $fieldValue < $condition->value;
                case ">=":
                    return $fieldValue !== null &&
                        $fieldValue >= $condition->value;
                case "<=":
                    return $fieldValue !== null &&
                        $fieldValue <= $condition->value;
                case "=":
                    return $fieldValue !== null &&
                        $fieldValue == $condition->value;
                case "!=":
                    return $fieldValue !== null &&
                        $fieldValue != $condition->value;
                case "IN":
                    return $fieldValue !== null &&
                        in_array($fieldValue, (array) $condition->value);
                case "NOT IN":
                    return $fieldValue !== null &&
                        !in_array($fieldValue, (array) $condition->value);
            }
        }

        // Par défaut, considérer la condition remplie
        return true;
    }

    /**
     * Récupère une valeur dans un tableau multidimensionnel via un chemin "dot notation"
     */
    protected function getNestedValue(array $data, string $path)
    {
        $keys = explode(".", $path);
        $value = $data;

        // throw new Exception(json_encode($keys), 1);

        foreach ($keys as $key) {
            //return $value;
            // Cas spécial : [] signifie "appliquer à tous les éléments du tableau"
            if ($key === "[]") {
                if (!is_array($value)) {
                    return null;
                }

                // On retourne un tableau des valeurs suivantes
                $remainingPath = implode(
                    ".",
                    array_slice($keys, array_search($key, $keys) + 1)
                );

                $results = [];
                foreach ($value as $item) {
                    $nested = $this->getNestedValue($item, $remainingPath);
                    if ($nested !== null) {
                        $results[] = $nested;
                    }
                }

                return is_array($results) ? $results : [$results]; // $results; // ex: [4, 6, 9]
                //return  $results; // ex: [4, 6, 9]
            }

            // throw new Exception(json_encode(is_array($value) && array_key_exists($key, $value)), 1);
            // return  array_key_exists($key, $value);
            // Cas normal
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
                // throw new Exception(json_encode($value), 1);
            } else {
                return null; // chemin inexistant
            }
        }

        //  return "ok";

        //  return is_array($value) ? $value : [$value];
        return $value;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowInstance $workflowInstance)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowInstance $workflowInstance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowInstanceRequest  $request
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateWorkflowInstanceRequest $request,
        WorkflowInstance $workflowInstance
    ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowInstance $workflowInstance)
    {
        //
    }
}
