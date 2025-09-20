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
use App\Models\WorkflowInstanceStepRoleDynamic;
use App\Models\WorkflowStatusHistory;
use App\Notifications\StepReminderNotification;
use App\Services\WorkflowInstanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Notification;

class WorkflowInstanceController extends Controller
{
    use ResolveDepartmentValidator;

    protected $workflowInstanceService;

    public function __construct(
        WorkflowInstanceService $workflowInstanceService
    ) {
        $this->workflowInstanceService = $workflowInstanceService;
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
                ],
             //   404
            );
        }

        return response()->json([
            "success" => true,
            "data" => $currentInstanceStep,
        ]);
    }

    public function getCurrentStep(
        WorkflowInstance $instance
    ): ?WorkflowInstanceStep {
        return $instance
            ->instance_steps()
            ->where("status", "PENDING")
            ->orderBy("position", "asc")
            ->first();
    }

    public function old_validateStep(Request $request, $documentId)
    {
        DB::beginTransaction();

        $user = $request->get("user");

        // 3️⃣ Récupérer l'instance globale
        $instance = WorkflowInstance::whereDocumentId($documentId)->first();

        // 1️⃣ Récupérer l'étape en cours
        $currentStep = $this->getCurrentStep($instance);

        // 2️⃣ Marquer l’étape comme validée
        $currentStep->update([
            "status" => "COMPLETE",
            "user_id" => $user["id"],
            "validated_at" => now(),
        ]);

        // 4️⃣ Déterminer l’étape suivante
        $nextStep = WorkflowInstanceStep::where(
            "workflow_instance_id",
            $instance->id
        )
            ->where("position", ">", $currentStep->position)
            ->orderBy("position", "asc")
            ->first();

        if ($nextStep) {
            // Activer la prochaine étape
            $nextStep->update([
                "status" => "PENDING",
            ]);

            // Mettre à jour l'instance comme "toujours en cours"
            $instance->update([
                "status" => "PENDING",
            ]);
        } else {
            // Pas d’étape suivante → Workflow terminé
            $instance->update([
                "status" => "COMPLETE",
            ]);
        }

        DB::commit();

        return response()->json([
            "success" => true,
            "message" => "Étape validée avec succès",
            "currentStep" => $currentStep,
        ]);

        try {
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    // Récupérer l'historique des étapes d'un document
    public function history($documentId)
    {
        // On suppose que workflow_instances est lié à documents
        $workflow = WorkflowInstance::where("document_id", $documentId)
            ->with([
                "instance_steps.workflowStep" => function ($q) {
                    // $q->select('id', 'name', 'email');
                },
            ])
            ->firstOrFail();

        // --- 1. Récupérer tous les role_id des steps ---
        $roleIds = $workflow->instance_steps
            ->pluck("role_id")
            ->unique()
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
                $roles = collect($responseRoles->json())->keyBy("id"); // id -> role data
            }
        }

        // --- 2. Récupérer les users seulement pour les étapes complétées ---
        $completedUserIds = $workflow->instance_steps
            ->whereIn("status", ["COMPLETE", "REJECTED"])
            ->pluck("user_id")
            ->unique()
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
                $users = collect($responseUsers->json())->keyBy("id"); // id -> user data
            }
        }

        // --- 3. Construire la timeline ---
        $steps = $workflow->instance_steps->map(function ($step) use (
            $users,
            $roles
        ) {
            $role = $roles[$step->role_id]["name"] ?? "Rôle inconnu";

            if (
                in_array($step->status, ["COMPLETE", "REJECTED"]) &&
                isset($users[$step->user_id])
            ) {
                $user = $users[$step->user_id];
                $displayName = $role . " (" . $user["name"] . ")";
            } else {
                // PENDING → afficher uniquement le rôle
                $displayName = $role;
            }

            return [
                "position" => $step->workflowStep->position,
                "validator" => $displayName,
                "status" => $step->status,
                "comment" => $step->comment,
                "acted_at" => $step->executed_at,
            ];
        })->sortBy('position')->values()->toArray();

        

        return response()->json([
            "document_id" => $documentId,
            "steps" => $steps,
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

    public function store(StoreWorkflowInstanceRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $userConnected = $validated["created_by"];

            $STATUS_NOT_STARTED = "NOT_STARTED";
            $STATUS_PENDING = "PENDING";
            $STATUS_COMPLETE = "COMPLETE";

            // 1️⃣ Créer l'instance de workflow
            $workflowInstance = WorkflowInstance::create([
                "workflow_id" => $validated["workflow_id"],
                "document_id" => $validated["document_id"],
                "status" => $STATUS_PENDING,
            ]);

            // 2️⃣ Créer toutes les étapes de l'instance
            $instanceSteps = [];

            foreach ($validated["steps"] as $index => $step) {
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
                } else {
                    if ($validated["department_id"]) {
                        # code...
                    
                    // récupération dynamique du rôle selon le département
                    $validatorRole = $this->getRoleValidator(
                        $validated["department_id"]
                    );
                    if ($validatorRole) {
                        $stepRoles = [$validatorRole["id"]];
 
                    }/**/
                        }else{

                            $stepRoles = [];

                        }
                }

                foreach ($stepRoles as $roleId) {
                    // Déterminer le statut initial
                    $initialStatus = $STATUS_NOT_STARTED;
                    $stepUserId = null;

                    if ($index === 0 && $roleId == $userConnected["role_id"]) {
                        $initialStatus = $STATUS_COMPLETE;
                        $stepUserId = $userConnected["id"];
                    } elseif ($index === 0) {
                        $initialStatus = $STATUS_PENDING;
                    }

                    $stepInstance = WorkflowInstanceStep::create([
                        "workflow_instance_id" => $workflowInstance->id,
                        "workflow_step_id" => $step["id"],
                        "role_id" => $roleId,
                        "user_id" => $stepUserId,
                        "status" => $initialStatus,
                        "due_date" => now()->addHours(
                            $step["delay_hours"] ?? 24
                        ), // ou delay_days
                        "executed_at" =>
                            $initialStatus == $STATUS_COMPLETE ? now() : null,
                        "position" => $step["position"],
                    ]);

                    $instanceSteps[$step["id"]][$roleId] = $stepInstance;

                    // 3️⃣ Créer l'entrée WorkflowInstanceStepRole pour les rôles dynamiques
                    if ($step["assignment_mode"] === "DYNAMIC") {
                        WorkflowInstanceStepRoleDynamic::create([
                            "workflow_instance_step_id" => $stepInstance->id,
                            "role_id" => $roleId,
                        ]);
                    }
                }
            }

            // 3️⃣ Activer toutes les premières étapes à exécuter (PENDING)
            // Trouver la position minimale des étapes non démarrées
            $minPosition = collect($instanceSteps)
                ->flatMap(fn($stepGroup) => $stepGroup)
                ->filter(
                    fn($stepInstance) => $stepInstance->status ===
                        $STATUS_NOT_STARTED
                )
                ->min(fn($stepInstance) => $stepInstance->position);

            // Mettre en PENDING uniquement les étapes à cette position
            $stepsToNotify = [];
            foreach ($instanceSteps as $stepGroup) {
                foreach ($stepGroup as $stepInstance) {
                    if (
                        $stepInstance->status === $STATUS_NOT_STARTED &&
                        $stepInstance->position === $minPosition
                    ) {
                        $stepInstance->update(["status" => $STATUS_PENDING]);
                        $stepsToNotify[] = $stepInstance; // stocker pour notification
                    }
                }
            }

            // 🔔 Ici : notifier les utilisateurs des étapes PENDING
            foreach ($stepsToNotify as $stepInstance) {
                //$roleId = $stepInstance->role_id;
                //$userId = $stepInstance->user_id;

                // Soit tu récupères l'utilisateur associé au rôle
                // soit tu envoies une notification au rôle directement
                $this->workflowInstanceService->notifyNextValidator(
                    $stepInstance,
                    $request,
                    $validated["department_id"]
                );
            }

            DB::commit();

            return response()->json(
                $workflowInstance->load("instance_steps"),
                201
            );

            /* return response()->json(["success"=>false,"data"=>["workfowInstance"=>
             $workflowInstance->load('instance_steps')]], 201);*/
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function old_store2(StoreWorkflowInstanceRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $userConnected = $validated["created_by"];

            $STATUS_NOT_STARTED = "NOT_STARTED";
            $STATUS_PENDING = "PENDING";
            $STATUS_COMPLETE = "COMPLETE";

            // 1️⃣ Créer l'instance de workflow
            $workflowInstance = WorkflowInstance::create([
                "workflow_id" => $validated["workflow_id"],
                "document_id" => $validated["document_id"],
                "status" => $STATUS_PENDING,
            ]);

            // 2️⃣ Créer toutes les étapes de l'instance
            $instanceSteps = [];

            foreach ($validated["steps"] as $index => $step) {
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
                } else {
                    // récupération dynamique du rôle selon le département
                    $validatorRole = $this->getRoleValidator(
                        $validated["department_id"]
                    );
                    if ($validatorRole) {
                        $stepRoles = [$validatorRole["id"]];
                    }
                }

                foreach ($stepRoles as $roleId) {
                    // Déterminer le statut initial
                    $initialStatus = $STATUS_NOT_STARTED;
                    $stepUserId = null;

                    if ($index === 0 && $roleId == $userConnected["role_id"]) {
                        $initialStatus = $STATUS_COMPLETE;
                        $stepUserId = $userConnected["id"];
                    } elseif ($index === 0) {
                        $initialStatus = $STATUS_PENDING;
                    }

                    $stepInstance = WorkflowInstanceStep::create([
                        "workflow_instance_id" => $workflowInstance->id,
                        "workflow_step_id" => $step["id"],
                        "role_id" => $roleId,
                        "user_id" => $stepUserId,
                        "status" => $initialStatus,
                        "due_date" => now()->addHours(
                            $step["delay_hours"] ?? 24
                        ), // ou delay_days
                        "executed_at" =>
                            $initialStatus == $STATUS_COMPLETE ? now() : null,
                        "position" => $step["position"],
                    ]);

                    $instanceSteps[$step["id"]][$roleId] = $stepInstance;

                    // 3️⃣ Créer l'entrée WorkflowInstanceStepRole pour les rôles dynamiques
                    if ($step["assignment_mode"] === "DYNAMIC") {
                        WorkflowInstanceStepRoleDynamic::create([
                            "workflow_instance_step_id" => $stepInstance->id,
                            "role_id" => $roleId,
                        ]);
                    }
                }
            }

            // 3️⃣ Activer toutes les premières étapes à exécuter (PENDING)
            // Trouver la position minimale des étapes non démarrées
            $minPosition = collect($instanceSteps)
                ->flatMap(fn($stepGroup) => $stepGroup)
                ->filter(
                    fn($stepInstance) => $stepInstance->status ===
                        $STATUS_NOT_STARTED
                )
                ->min(fn($stepInstance) => $stepInstance->position);

            // Mettre en PENDING uniquement les étapes à cette position
            $stepsToNotify = [];
            foreach ($instanceSteps as $stepGroup) {
                foreach ($stepGroup as $stepInstance) {
                    if (
                        $stepInstance->status === $STATUS_NOT_STARTED &&
                        $stepInstance->position === $minPosition
                    ) {
                        $stepInstance->update(["status" => $STATUS_PENDING]);
                        $stepsToNotify[] = $stepInstance; // stocker pour notification
                    }
                }
            }

            // 🔔 Ici : notifier les utilisateurs des étapes PENDING
            foreach ($stepsToNotify as $stepInstance) {
                //$roleId = $stepInstance->role_id;
                //$userId = $stepInstance->user_id;

                // Soit tu récupères l'utilisateur associé au rôle
                // soit tu envoies une notification au rôle directement
                $this->workflowInstanceService->notifyNextValidator(
                    $stepInstance,
                    $request,
                    $validated["department_id"]
                );
            }

            DB::commit();

            return response()->json(
                $workflowInstance->load("instance_steps"),
                201
            );

            /* return response()->json(["success"=>false,"data"=>["workfowInstance"=>
             $workflowInstance->load('instance_steps')]], 201);*/
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
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

    public function store2(StoreWorkflowInstanceRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $userConnected = $validated["created_by"];

            $STATUS_NOT_STARTED = "NOT_STARTED";
            $STATUS_PENDING = "PENDING";
            $STATUS_COMPLETE = "COMPLETE";

            // 1️⃣ Créer l'instance de workflow
            $workflowInstance = WorkflowInstance::create([
                "workflow_id" => $validated["workflow_id"],
                "document_id" => $validated["document_id"],
                "status" => $STATUS_PENDING,
            ]);

            // 2️⃣ Créer toutes les étapes de l'instance
            $instanceSteps = [];
            $userRoleId = $userConnected["role_id"];

            foreach ($validated["steps"] as $index => $step) {
                if ($index === 0 && $step["role_id"] === $userRoleId) {
                    $initialStatus = $STATUS_COMPLETE; // l'utilisateur réalise l'étape dès la création
                    $stepUserId = $userConnected["id"];
                } elseif ($index === 0) {
                    $initialStatus = $STATUS_PENDING; // première étape à réaliser par un autre
                    $stepUserId = null;
                } else {
                    $initialStatus = $STATUS_NOT_STARTED; // étapes suivantes
                    $stepUserId = null;
                }

                $stepInstance = WorkflowInstanceStep::create([
                    "workflow_instance_id" => $workflowInstance->id,
                    "workflow_step_id" => $step["id"],
                    "role_id" =>
                        $step["assignment_mode"] == "STATIC"
                            ? $step["role_id"] ?? null
                            : $this->getRoleValidator(
                                $validated["department_id"]
                            )["id"],
                    "user_id" => $stepUserId,
                    "status" => $initialStatus,
                    "executed_at" =>
                        $initialStatus == $STATUS_COMPLETE ? now() : null,
                    "position" => $step["position"],
                ]);

                $instanceSteps[$step["id"]] = $stepInstance;
            }

            // 3️⃣ Déterminer et activer la première étape à exécuter
            $nextStep = $workflowInstance
                ->instance_steps()
                ->where("status", $STATUS_NOT_STARTED)
                ->orderBy("position")
                ->first();

            if ($nextStep) {
                $nextStep->update([
                    "status" => $STATUS_PENDING,
                ]);

                // notifier le user assigné
            }

            // 4️⃣ Optionnel : créer un historique des transitions initiales si tu veux précharger les transitions
            foreach ($validated["steps"] as $index => $step) {
                $transitions = WorkflowTransition::where(
                    "from_step_id",
                    $step["id"]
                )->get();
                foreach ($transitions as $transition) {
                    // Ici tu peux stocker dans un journal ou préparer des notifications
                    // Pas besoin de changer le statut maintenant, les conditions seront évaluées lors de la validation
                }
            }

            DB::commit();

            return response()->json(
                $workflowInstance->load("instance_steps"),
                201
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function old_store(StoreWorkflowInstanceRequest $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $userConnected = $validated["created_by"];
            $STATUS_NOT_STARTED = "NOT_STARTED";
            $STATUS_PENDING = "PENDING";
            $STATUS_COMPLETE = "COMPLETE";

            $workflowInstance = WorkflowInstance::create([
                "workflow_id" => $validated["workflow_id"],
                "document_id" => $validated["document_id"],
                "status" => "PENDING",
            ]);

            //  $department_position = $this->resolveDepartmentValidator($validated["department_id"]) ;
            // return  $role = $this->resolveRoleValidator($department_position['position']['name'])['results'] ;

            //  return $validated['steps'];

            // 4️⃣ Créer les étapes de l'instance
            // return $step;
            $userRoleId = $userConnected["role_id"]; // ou $userConnected->role_id selon ton modèle

            foreach ($validated["steps"] as $index => $step) {
                if ($index === 0 && $step["role_id"] === $userRoleId) {
                    $initialStatus = $STATUS_COMPLETE; // l'utilisateur réalise l'étape dès la création
                    $stepUserId = $userConnected["id"];
                } elseif ($index === 0) {
                    $initialStatus = $STATUS_PENDING; // première étape à réaliser par un autre
                    $stepUserId = null;
                } else {
                    $initialStatus = $STATUS_NOT_STARTED; // les étapes suivantes ne sont pas encore activées
                    $stepUserId = null;
                }

                $step_instance = WorkflowInstanceStep::create([
                    "workflow_instance_id" => $workflowInstance->id,
                    "workflow_step_id" => $step["id"],
                    "role_id" =>
                        $step["assignment_mode"] == "STATIC"
                            ? $step["role_id"] ?? null
                            : $this->getRoleValidator(
                                $validated["department_id"]
                            )["id"],
                    "user_id" => $stepUserId,
                    "status" => $initialStatus,
                    "position" => $step["position"], // copie depuis le template
                ]);

                if ($step["assignment_mode"] != "STATIC") {
                    // return $step_instance;
                }
            }

            $nextStep = $workflowInstance
                ->instance_steps()
                ->where("status", $STATUS_NOT_STARTED)
                ->orderBy("position")
                ->first();

            if ($nextStep) {
                $nextStep->update([
                    "status" => $STATUS_PENDING,
                ]);

                // notifier le user assigné
            }

            DB::commit();

            return response()->json($workflowInstance, 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function getDocumentData(WorkflowInstance $instance, $request): array
    {
        // 🔹 Récupérer les données du document depuis le microservice
        $response = Http::withToken($request->bearerToken())
            ->acceptJson()
            ->get(
                config("services.document_service.base_url") .
                    "/{$instance->document_id}"
            );

        if (!$response->successful()) {
            throw new \Exception(
                "Impossible de récupérer le document : " . $response->status()
            );
        }

        $documentData = $response->json();

        return $documentData; //->toArray();
    }

    public function checkIfHasBlocker(Request $request, $documentId)
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
            $currentStep = $this->getCurrentStep($instance);

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

            // 🔹 Vérifier les règles de blocage avant validation
            // return  
              $blockingData = $this->checkBlockingRules(
                $instance,
                $currentStep,
                $documentData
            );

            if (!$blockingData["isValid"] /*&& false*/) {
                return response()->json([
                    "success" => false,
                    "data" => $blockingData["data"],
                ]);
            }

            DB::commit();

            // 4️⃣ Déterminer l’étape suivante via les transitions conditionnelles
            $stepData = $this->getNextStep(
                $instance,
                $currentStep,
                $documentData,
                $action
            );

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
            return response()->json(
                [
                    "success" => false,
                    "message" => $th->getMessage(),
                ],
                500
            );
        }
    }

    public function validateStep(Request $request, $documentId)
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
            $currentStep = $this->getCurrentStep($instance);
            $oldStatus = $currentStep->status;

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

            // 3️⃣ Marquer l’étape comme validée
            $currentStep->update([
                "status" => "COMPLETE",
                "user_id" => $user["id"],
                "executed_at" => now(),
                "validated_at" => now(),
            ]);

            // 4️⃣ Déterminer l’étape suivante via les transitions conditionnelles
            $stepData = $this->getNextStep(
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
                // Activer la prochaine étape
                $nextStep->update([
                    "status" => "PENDING",
                ]);

                //$newStatus = "PENDING";

                // Mettre à jour l'instance comme "toujours en cours"
                $instance->update([
                    "status" => "PENDING",
                ]);

                $this->workflowInstanceService->notifyNextValidator(
                    $nextStep,
                    $request,
                    $request->get("department_id")
                );
            } else {
                // Pas d’étape suivante → Workflow terminé
                $instance->update([
                    "status" => "COMPLETE",
                ]);

                //$newStatus = "COMPLETE";
            }

            // 🔹 Historisation
           

            $historyData = [
    "model_id"   => $currentStep->id,
    "model_type" => get_class($currentStep),
    "changed_by" => $user["id"],
    "old_status" => $oldStatus,
    "new_status" => $currentStep->status == "COMPLETE"
        ? "COMPLETED"
        : $currentStep->status,
    "comment"    => $request->get("comment"),
];

// Supprimer les clés avec valeur null
$historyData = array_filter($historyData, fn($v) => !is_null($v));

$history = WorkflowStatusHistory::create($historyData);

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "Étape validée avec succès",
                "currentStep" => $currentStep,
                "nextStep" => $nextStep,
                "history" => $history,
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
                        "required_type"=>$rule->required_type
                    ],
                ];

                throw new \Exception(
                    "Étape bloquée : Vous devez joindre l'engagement de ce document ({$rule->condition_type})"
                );
            }
        }

        return ["isValid" => true, "data" => ["message" => ""]];
    }

    protected function getNextStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        array $documentData
    ) {
        $isDynamic = false;
        // Récupère les transitions depuis l'étape courante
        $transitions = WorkflowTransition::where(
            "from_step_id",
            $currentStep->workflow_step_id
        )->get();

        foreach ($transitions as $transition) {
            // Récupère les conditions PATH associées à la transition
            $pathConditions = WorkflowCondition::where(
                "workflow_transition_id",
                $transition->id
            )
                ->where("condition_kind", "PATH")
                ->get();

            $allSatisfied = true;

            foreach ($pathConditions as $condition) {
                if (!$this->evaluateCondition($condition, $documentData)) {
                    $allSatisfied = false;
                    break; // une seule condition PATH non remplie → on ignore cette transition
                }
            }

            if (!$allSatisfied) {
                continue;
            }

            //return "{$instance->id} {$transition->to_step_id}";
            // Toutes les conditions PATH sont remplies → on retourne la prochaine étape
            $tempWorkflowInstanceStep = WorkflowInstanceStep::where(
                "workflow_instance_id",
                $instance->id
            )
                ->where("workflow_step_id", $transition->to_step_id)
                ->first();

            if ($transition->to_step_id && !$tempWorkflowInstanceStep) {
                //il y'a un etape dynamique

                $isDynamic = true;
            } else {
                return [
                    "isDynamic" => $isDynamic,
                    "next_step" => $tempWorkflowInstanceStep,
                ];
            }
        }

        // Aucune transition valide
        return ["isDynamic" => $isDynamic, "next_step" => null];
    }

    protected function old_getNextStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        array $documentData,
        string $action
    ) {
        //: ?WorkflowInstanceStep
        // return $action;

        $transitions = WorkflowTransition::with("conditions")
            ->where("from_step_id", $currentStep->workflow_step_id)
            ->whereType($action)
            ->whereType("expression")
            ->orderBy("id")
            ->get();

        foreach ($transitions as $transition) {
            if (
                $transition->condition &&
                !$this->evaluateCondition($transition->condition, $documentData)
            ) {
                continue;
            }

            return WorkflowInstanceStep::where(
                "workflow_instance_id",
                $instance->id
            )
                ->where("workflow_step_id", $transition->to_step_id)
                ->first();
        }

        return null; // Pas d'étape suivante
    }
    /**
     * Retourne l'étape suivante selon les transitions et conditions
     */
    protected function getNexasdtStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        $request,
        $action
    ) {
        try {
            // 🔹 Récupérer les données du document depuis le microservice
            $response = Http::withToken($request->bearerToken())
                ->acceptJson()
                ->get(
                    config("services.document_service.base_url") .
                        "/{$instance->document_id}"
                );

            if (!$response->successful()) {
                throw new \Exception(
                    "Impossible de récupérer le document : " .
                        $response->status()
                );
            }

            $documentData = $response->json();

            // 🔹 Récupérer toutes les transitions possibles depuis l'étape courante
            return $currentStep;

            $transitions = WorkflowTransition::with("conditions")
                ->where("from_step_id", $currentStep->workflow_step_id)
                ->whereType($action)
                ->orderBy("id")
                ->get();

            foreach ($transitions as $transition) {
                $conditions = $transition->conditions;

                // 1️⃣ Vérifier les BLOCKING rules
                $blockingRules = $conditions->where(
                    "condition_kind",
                    "BLOCKING"
                );
                $blocked = false;
                foreach ($blockingRules as $rule) {
                    if (!$this->evaluateCondition($rule, $documentData)) {
                        $blocked = true;
                        break; // si une règle blocking n'est pas remplie, on bloque cette transition
                    }
                }
                if ($blocked) {
                    continue; // passer à la transition suivante
                }

                // 2️⃣ Vérifier les PATH rules pour déterminer l'étape suivante
                $pathRules = $conditions->where("condition_kind", "PATH");

                foreach ($pathRules as $rule) {
                    if ($this->evaluateCondition($rule, $documentData)) {
                        return WorkflowInstanceStep::where(
                            "workflow_instance_id",
                            $instance->id
                        )
                            ->where("workflow_step_id", $rule->next_step_id)
                            ->first();
                    }
                }

                // 3️⃣ Si aucune PATH rule n'est remplie, utiliser le to_step_id par défaut de la transition
                if ($transition->to_step_id) {
                    return WorkflowInstanceStep::where(
                        "workflow_instance_id",
                        $instance->id
                    )
                        ->where("workflow_step_id", $transition->to_step_id)
                        ->first();
                }
            }

            // 🔹 Pas d'étape suivante → workflow terminé
            return null;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new \Exception(
                "Erreur de communication avec le microservice Document : " .
                    $e->getMessage()
            );
        } catch (\Throwable $e) {
            throw new \Exception(
                "Erreur lors de la récupération du document : " .
                    $e->getMessage()
            );
        }
    }

    protected function old_getNepxtStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $currentStep,
        $request,
        $action
    ) {
        try {
            $response = Http::withToken($request->bearerToken())
                ->acceptJson()
                ->get(
                    config("services.document_service.base_url") .
                        "/{$instance->document_id}"
                );

            if (!$response->successful()) {
                // Erreur côté service Document
                throw new \Exception(
                    "Impossible de récupérer le document : " .
                        $response->status()
                );
            }

            /*if (empty($documentData)) {
                            throw new \Exception("Document introuvable ou réponse vide du microservice");
                        }*/

            $documentData = $response->json();

            $transitions = WorkflowTransition::with("conditions")
                ->where("from_step_id", $currentStep->workflow_step_id)
                ->whereType($action)
                ->orderBy("id")
                ->get();

            foreach ($transitions as $transition) {
                // Si la transition a une condition
                if ($transition->condition) {
                    $condition = $transition->condition;
                    if (!$this->evaluateCondition($condition, $documentData)) {
                        continue; // Condition non remplie → passer à la transition suivante
                    }
                }

                // Retourner la step cible de la transition
                return WorkflowInstanceStep::where(
                    "workflow_instance_id",
                    $instance->id
                )
                    ->where("workflow_step_id", $transition->to_step_id)
                    ->first();
            }

            // Pas d'étape suivante
            return null;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Erreur réseau / timeout
            throw new \Exception(
                "Erreur de communication avec le microservice Document : " .
                    $e->getMessage()
            );
        } catch (\Throwable $e) {
            // Toute autre erreur
            throw new \Exception(
                "Erreur lors de la récupération du document : " .
                    $e->getMessage()
            );
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
       $fieldValue = $this->getNestedValue($data, $condition->field);



// Convertir les chaînes en entiers si nécessaire
$haystack_int = array_map('intval', $condition->required_id);


            //throw new Exception(json_encode($fieldValue), 1);
            //throw new Exception(json_encode($haystack_int), 1);


        // Si le type de condition est 'exists' (vérifie la présence d'un document ou d'une valeur)
        if ($condition->condition_type === "exists") {


            if (is_array($fieldValue)) {

                return (!empty($fieldValue) &&
                !empty(array_intersect($fieldValue, $haystack_int)));
            } else {
               
            //throw new Exception(json_encode($fieldValue), 1);
            //throw new Exception(json_encode($haystack_int), 1);
           // throw new Exception(json_encode(in_array($fieldValue , $haystack_int) ), 1);

                return   ( in_array($fieldValue , $haystack_int) );

            }
            

            


        }

        // Si le type de condition est 'userRole' (exemple : vérifier le rôle du soumissionnaire)
        if ($condition->condition_type === "userRole") {
            return isset($data["user"]["roles"]) &&
                in_array($condition->value, $data["user"]["roles"]);
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

    protected function old_evaluatepCondition(
        WorkflowCondition $condition,
        array $data
    ) {
        $fieldValue = $this->getNestedValue($data, $condition->field);

        switch ($condition->operator) {
            case ">":
                return $fieldValue !== null &&
                    $fieldValue > (float) $condition->value;
            case "<":
                return $fieldValue !== null &&
                    $fieldValue < (float) $condition->value;
            case "=":
                return $fieldValue !== null && $fieldValue == $condition->value;
            case "!=":
                return $fieldValue !== null && $fieldValue != $condition->value;
            default:
                return true;
        }
    }

    /**
     * Récupère une valeur dans un tableau multidimensionnel via un chemin "dot notation"
     */
    protected function getNestedValue(array $data, string $path)
    {
        $keys = explode(".", $path);
        $value = $data;

        

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

                return is_array($results) ? $results : [$results];// $results; // ex: [4, 6, 9]
                //return  $results; // ex: [4, 6, 9]
            }

           // return  array_key_exists($key, $value);
            // Cas normal
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null; // chemin inexistant
            }
        }

        

      //  return "ok";

      //  return is_array($value) ? $value : [$value];
        return $value ;
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
