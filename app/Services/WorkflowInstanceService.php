<?php
namespace App\Services;

use App\Enums\NotificationPolicy;
use App\Models\DocumentTypeWorkflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowInstanceStepAssignment;
use App\Models\WorkflowStatusHistory;
use App\Models\WorkflowStatusLabel;
use App\Models\WorkflowStepRole;
use App\Services\Workflow\WorkflowInstanceResolverService;
use Exception;
use Illuminate\Support\Facades\Http;

class WorkflowInstanceService
{
    use ResolveDepartmentValidator;

    protected WorkflowInstanceResolverService $resolver;

    public function __construct(
        WorkflowInstanceResolverService $workflowInstanceResolverService
    ) {
        $this->resolver = $workflowInstanceResolverService;
    }


    public function resetStep(
    WorkflowInstanceStep $step
): void
{
    $step->update([
        'status' => 'PENDING',
        'executed_at' => null,
        // 'comments' => null,
    ]);
}

public function resetInstanceSteps(
    WorkflowInstance $instance,
    WorkflowInstanceStep $targetStep
): void
{
    WorkflowInstanceStep::where('workflow_instance_id', $instance->id)
        ->where('position', '>', $targetStep->position)
        ->each(function ($step) {

            $step->update([
                'status' => 'NOT_STARTED',
                'executed_at' => null,
                'comments' => null,
            ]);

            $this->resetAssignSteps($step);
        });
}

public function resetTargetStep(
    WorkflowInstanceStep $step
): void
{
    $step->update([
        'status' => 'PENDING',
        'executed_at' => null,
        'comments' => null,
        'workflow_status_label_id' => WorkflowStatusLabel::whereCode("RETURNED_FOR_MODIFICATION")->first()->id 

    ]);

    $this->resetAssignSteps($step);
}




private function resetAssignSteps(
    WorkflowInstanceStep $instanceStep
): void
{
    $data = $instanceStep->workflowStep->assignment_mode == "OWNER" ? ['decision' => "PENDING" , 'decided_at'=>null] : 
    [
        'user_id'=> null,
        'decision' => "PENDING",
         'decided_at'=>null
    ];

    WorkflowInstanceStepAssignment::where(
        'instance_step_id',
        $instanceStep->id
    )
    ->update($data);
}


public function isReturnedForModification(
    WorkflowInstance $instance
): bool
{
    $currentStep = $this->resolver->getCurrentStep($instance);

    if (!$currentStep) {
        return false;
    }

    if ($currentStep->position !== 0) {
        return false;
    }

    return WorkflowStatusHistory::where('model_id', $instance->id)
        ->where('model_type', WorkflowInstance::class)
        ->where('new_status', 'RETURNED_FOR_MODIFICATION')
        ->exists();
}
public function cancelable(WorkflowInstance $instance): bool
{
    // workflow déjà terminé
    if (in_array($instance->status, [
        'COMPLETE',
        'REJECTED',
        'CANCELLED',
    ])) {
        return false;
    }



    // throw new Exception(json_encode('$instance'), 1);


    // une validation (hors soumission) a déjà eu lieu
    if (
        $instance->instance_steps()
            ->where('position', '>', 0)
            ->where('status', 'COMPLETE')
            ->exists()
    ) {

        
        return false;
        
        
        }
        
        // throw new Exception("Error Processing Request", 1);
    

    return true;
}

public function cancel(
    WorkflowInstance $instance,
    int $userId,
    string $reason 
) {

    $instance->update([
        'status' => 'CANCELLED',
    ]);

    // return 
    // WorkflowStatusLabel::where(
    //     'code',
    //     'CANCELLED'
    // )->first();



    $instance->instance_steps()
        ->whereIn('status', [
            'PENDING',
            'NOT_STARTED'
        ])
        ->update([
            'status' => 'CANCELLED'
        ]);


    // WorkflowStatusHistory::create([
    //     'workflow_instance_id' => $instance->id,
    //     'action' => 'CANCELLED',
    //     'user_id' => $userId,
    //     'comment' => $reason,
    // ]);

    WorkflowStatusHistory::create([
         'model_id' => $instance->id,
            'model_type' => WorkflowInstance::class,
            'old_status' => 'PENDING',
            'new_status' => 'CANCELLED',
            'changed_by' => $userId,
            'comment' => $reason,
    ]);
    
}

protected function resolveNotificationUserIds(
    array $identifiers,
    string $policy,
    $request
): array {

    if (empty($identifiers)) {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    | Les identifiants sont déjà des user_id.
    */

    if ($policy === "USER") {

        return collect($identifiers)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE
    |--------------------------------------------------------------------------
    | Les identifiants sont des role_id.
    |--------------------------------------------------------------------------
    */

    if ($policy === "ROLE") {

        $response = Http::acceptJson()
            ->withToken($request->bearerToken())
            ->post(
                config("services.user_service.base_url") .
                "/roles/users",
                [
                    "role_ids" => $identifiers,
                ]
            );

        if (!$response->successful()) {
            throw new Exception(
                json_encode($response->body()),
                $response->status()
            );
        }

        return collect(
            $response->json("data", [])
        )
            ->pluck("id")
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    throw new Exception(
        "Politique de notification inconnue : {$policy}"
    );
}

protected function resolveNotificationTargets(
    WorkflowInstanceStep $stepInstance,
    $request
): array {

    $assignments = $stepInstance->assignments()
        ->get();

    /*
    |--------------------------------------------------------------------------
    | 1. Assignations utilisateur explicites
    |--------------------------------------------------------------------------
    */

    $assignedUserIds = $assignments
        ->pluck("assigned_user_id")
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    // throw new Exception(json_encode($assignedUserIds), 1);
    

    if (!empty($assignedUserIds)) {
        return [
            "policy" => NotificationPolicy::USER,
            "user_ids" => $assignedUserIds,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Assignation par rôle
    |--------------------------------------------------------------------------
    */

    $roleIds = $assignments
        ->pluck("role_id")
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    if (empty($roleIds)) {
        return [
            "policy" => NotificationPolicy::ROLE,
            "user_ids" => [],
        ];
    }

    $response = Http::acceptJson()
        ->withToken($request->bearerToken())
        ->post(
            config("services.user_service.base_url") .
            "/roles/users",
            [
                "role_ids" => $roleIds,
            ]
        );

    if (!$response->successful()) {
        throw new Exception(
            json_encode($response->body()),
            $response->status()
        );
    }

    $userIds = collect(
        $response->json("data", [])
    )
        ->pluck("id")
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    return [
        "policy" => NotificationPolicy::ROLE,
        "user_ids" => $userIds,
    ];
}

public function notifyNextValidators(
    WorkflowInstanceStep $stepInstance,
    $request,
    $departmentId = null
) {
    $workflowInstance = $stepInstance->workflowInstance;

    $documentId = $workflowInstance->document_uuid;
    $workflowId = $workflowInstance->workflow_id;

    /*
    |--------------------------------------------------------------------------
    | Déterminer les destinataires
    |--------------------------------------------------------------------------
    */

    $notificationTargets = $this->resolveNotificationTargets(
        $stepInstance,
        $request
    );

    if (empty($notificationTargets["user_ids"])) {
        return;
    }

    $userIds = $notificationTargets["user_ids"];

    // throw new Exception(json_encode($notificationTargets), 1);


    /*
    |--------------------------------------------------------------------------
    | Document type
    |--------------------------------------------------------------------------
    */

    $documentTypeWorkflow = DocumentTypeWorkflow::where(
        "workflow_id",
        $workflowId
    )->first();

    $documentTypeId = $documentTypeWorkflow
        ? $documentTypeWorkflow->document_type_id
        : null;

    /*
    |--------------------------------------------------------------------------
    | Récupérer le document
    |--------------------------------------------------------------------------
    */

    $response = Http::acceptJson()
        ->withToken($request->bearerToken())
        ->get(
            config("services.document_service.base_url") .
            "/{$documentId}"
        );

    if (!$response->successful()) {
        throw new Exception(
            json_encode($response->body()),
            $response->status()
        );
    }

    $documentData = $response->json();

    /*
    |--------------------------------------------------------------------------
    | Construire le message
    |--------------------------------------------------------------------------
    */

    $messageRegistry = new WorkflowNotificationMessageRegistry();

    $messageBuilder = $messageRegistry->resolve(
        $documentData["document_type"]["slug"]
    );

    $payload = $messageBuilder->build($documentData);

    /*
    |--------------------------------------------------------------------------
    | Notification
    |--------------------------------------------------------------------------
    */

    $notifyResponse = Http::acceptJson()
        ->withToken($request->bearerToken())
        ->post(
            config("services.user_service.base_url") .
            "/notifications",
            [
                "user_ids" => $userIds,
                "payload" => $payload,
                "document_id" => $documentId,
                "document_type_id" => $documentTypeId,
            ]
        );

    if (!$notifyResponse->successful()) {
        throw new Exception(
            json_encode($notifyResponse->body()),
            $notifyResponse->status()
        );
    }
}

    public function OldnotifyNextValidator(
        WorkflowInstanceStep $stepInstance,
        $request,
        $departmentId = "",
        $stepRoles = [],
         string $notificationPolicy = "ROLE"
    ) {
        // $step = $stepInstance->load("workflowStep")->workflowStep;
        // $stepRoles = [];

        // Vérifier si l'étape est PENDING
        if ($stepInstance->status !== "PENDING") {
            ///  return;
        }


        $workflowInstance = $stepInstance->workflowInstance;
        $documentId = $workflowInstance->document_uuid;
        $stepName = $stepInstance->workflowStep->name;

        $workflowId = $workflowInstance->workflow_id;

        // Récupérer le type de document associé au workflow
        $documentTypeWorkflow = DocumentTypeWorkflow::where(
            "workflow_id",
            $workflowId
        )->first();

        $documentTypeId = $documentTypeWorkflow
            ? $documentTypeWorkflow->document_type_id
            : null; // null si pas trouvé



        //["oui"];

        
        $response = Http::acceptJson()
            ->withToken($request->bearerToken()) // on passe le JWT si nécessaire
            ->get(
                config("services.document_service.base_url") . "/{$documentId}"
            );

        // throw new Exception(json_encode($response->body()), 1);

        $payload = [];
        if ($response->successful()) {
            $documentData = $response->json();

        // throw new Exception(json_encode($documentData), 1);





            $messageRegistry = new WorkflowNotificationMessageRegistry();
            $messageBuilder = $messageRegistry->resolve($documentData["document_type"]["slug"]);

            
            $payload = $messageBuilder->build($documentData);

            // throw new Exception(json_encode($payload), 1);





            // // supposer que l’API renvoie { "id": 123, "title": "Facture Proforma - Mars 2025", ... }
            // $documentTitle = $documentData["title"] ?? "Document sans titre";

            // $message = sprintf(
            //     //"📂 Vous êtes le prochain validateur pour l'étape '%s' du document #%d : « %s ».",
            //     "📂 Vous avez un nouveau document à traiter : « %s ».",
            //     // $stepName,
            //     // $documentId,
            //     $documentTitle
            // );
        } else {


        // throw new Exception(json_encode($documentId), 1);

        throw new Exception(json_encode($response->body()), 1);

            // fallback si le service ne répond pas
            $message = sprintf(
                //"📂 Vous êtes le prochain validateur pour l'étape '%s' du document #%d.",
                "📂 Vous êtes le prochain validateur pour l'étape '%s' du document #%d.",
                $stepName,
                $documentId
            );
        }

        // Si l'utilisateur est assigné (statique)
        if (false) {
        } else {
            // Si étape dynamique : récupérer tous les utilisateurs du rôle
            //$roleId =  $stepInstance->role_id;
            $response = Http::acceptJson()
                ->withToken($request->bearerToken())
                ->post(
                    config("services.user_service.base_url") . "/roles/users",
                    [
                        "role_ids" => $stepRoles,
                    ]
                );

            // throw new Exception(json_encode($response->successful()), 1);

            if ($response->successful()) {
                $users = $response->json()["data"];


              
                // throw new Exception(json_encode($users), 1);

                // Récupérer juste les IDs
                $userIds = collect($users)
                    ->pluck("id")
                    ->toArray();

                    //   return [
                    //     "user_ids" => $userIds,
                    //     "payload" => $payload,
                    //     "document_id" => $documentId,
                    //     "document_type_id" => $documentTypeId,
                    // ];

                  
                    // return
                // Notifier en une seule requête
                $notifyResponse = Http::acceptJson()->withToken($request->bearerToken())->post(
                    config("services.user_service.base_url") . "/notifications",
                    [
                        "user_ids" => $userIds,
                        "payload" => $payload,
                        "document_id" => $documentId,
                        "document_type_id" => $documentTypeId,
                    ]
                );

                if (!$notifyResponse->ok()) {
                    # code...
                    throw new Exception(json_encode($notifyResponse->body()), 1);
                }
                else{

                    // throw new Exception(json_encode($notifyResponse->ok()), 1);


                }




            }
        }
    }


    

}
