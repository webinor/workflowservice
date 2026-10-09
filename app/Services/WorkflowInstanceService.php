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
    protected ResponsibilityService $responsibilityService;

    public function __construct(
        WorkflowInstanceResolverService $workflowInstanceResolverService,
        ResponsibilityService $responsibilityService

    ) {
        $this->resolver = $workflowInstanceResolverService;
        $this->responsibilityService = $responsibilityService;

    }

    /**
 * Récupère la date et l'heure de clôture du workflow.
 *
 * La clôture est déterminée par l'étape dont la définition
 * possède is_archived_step = true.
 *
 * @param WorkflowInstance $instance
 *
 * @return string|null Date de clôture ou null si le workflow
 *                     n'a pas encore été clôturé.
 */
public function getWorkflowClosedAt(
    WorkflowInstance $instance
): ?string {

    /*
    |--------------------------------------------------------------------------
    | 1. Récupérer l'étape de clôture de l'instance
    |--------------------------------------------------------------------------
    |
    | workflowStep correspond à la définition de l'étape.
    | is_archived_step indique qu'il s'agit de l'étape d'archivage.
    |
    */

    $archiveStep = $instance->instance_steps()
        ->whereHas('workflowStep', function ($query) {
            $query->where('is_archived_step', true);
        })
        ->where('status', 'COMPLETE')
        ->orderByDesc('position')
        ->first();

    /*
    |--------------------------------------------------------------------------
    | 2. Vérifier que l'étape a été exécutée
    |--------------------------------------------------------------------------
    */

    if (!$archiveStep || !$archiveStep->executed_at) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Retourner la date et l'heure de clôture
    |--------------------------------------------------------------------------
    */

    return $archiveStep->executed_at->toDateTimeString();
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
    $data = $instanceStep->workflowStep->assignment_mode == "OWNER" && 
    !$instanceStep->workflowStep->is_regularization_start
    ? ['decision' => "PENDING" , 'decided_at'=>null] : 
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

/**
 * Détermine les destinations de retour autorisées.
 *
 * Règles :
 *
 * 1. Si la phase de régularisation n'a pas encore été atteinte :
 *    retour uniquement à la première étape du workflow.
 *
 * 2. Si la phase de régularisation a été atteinte :
 *    retour vers les étapes COMPLETE de cette phase,
 *    antérieures à l'étape courante.
 *
 * Le début de la phase est déterminé par :
 * workflow_steps.is_regularization_start = true
 *
 * @param WorkflowInstance $instance
 * @param WorkflowInstanceStep $currentStep
 *
 * @return \Illuminate\Support\Collection
 */
public function OldgetAllowedReturnSteps(
    $instance,
    $currentStep
) {
    /*
     * Récupérer les étapes exécutées de cette instance
     * et charger leurs définitions.
     */
    $instanceSteps = $instance->instance_steps()
        ->with('workflowStep')
        ->orderBy('position', 'asc')
        ->get();

    /*
     * Identifier le début de la régularisation
     * à partir du flag de la définition de l'étape.
     */
    $regularizationStart = $instanceSteps->first(
        function ($instanceStep) {
            return $instanceStep->workflowStep
                && $instanceStep->workflowStep->is_regularization_start;
        }
    );

    /*
     * Identifier la première étape du workflow.
     */
    $firstInstanceStep = $instanceSteps->first(
        function ($instanceStep) {
            return (int) $instanceStep->position === 0;
        }
    );

    /*
     * Si aucune étape de début n'est disponible,
     * retourner une collection vide.
     */
    if (!$firstInstanceStep) {
        return collect();
    }

    /*
     * Si le début de la régularisation n'a pas encore
     * été atteint, le retour est limité à la soumission.
     */
    if (!$regularizationStart) {
        return collect([$firstInstanceStep]);
    }

    /*
     * Si l'étape courante précède le début de la
     * régularisation, seul le retour à la soumission
     * est autorisé.
     */
    if (
        (int) $currentStep->position
        < (int) $regularizationStart->position
    ) {
        return collect([$firstInstanceStep]);
    }

    /*
     * Dans la phase de régularisation :
     *
     * - conserver uniquement les étapes COMPLETE ;
     * - inclure l'étape de début de régularisation ;
     * - exclure l'étape courante ;
     * - conserver l'ordre du parcours.
     */
    return $instanceSteps
        ->filter(function ($step) use (
            $regularizationStart,
            $currentStep
        ) {
            return $step->status === 'COMPLETE'
                && (int) $step->position
                    >= (int) $regularizationStart->position
                && (int) $step->position
                    < (int) $currentStep->position;
        })
        ->sortBy('position')
        ->values();
}

/**
 * Détermine les destinations de retour autorisées.
 *
 * Règles :
 *
 * 1. Si aucune étape de régularisation n'existe :
 *    aucune destination personnalisée n'est proposée.
 *    Le retour sera effectué au début du workflow.
 *
 * 2. Si la régularisation n'a pas encore été atteinte :
 *    aucune destination personnalisée n'est proposée.
 *    Le retour sera effectué au début du workflow.
 *
 * 3. Si la régularisation a commencé :
 *    proposer les étapes COMPLETE de cette phase,
 *    antérieures à l'étape courante.
 *
 * Le début de la phase est déterminé par :
 * workflow_steps.is_regularization_start = true
 *
 * @param WorkflowInstance $instance
 * @param WorkflowInstanceStep $currentStep
 *
 * @return \Illuminate\Support\Collection
 */
public function getAllowedReturnSteps(
    $instance,
    $currentStep
) {
    /*
    |--------------------------------------------------------------------------
    | 1. Récupérer les étapes de l'instance
    |--------------------------------------------------------------------------
    */

    $instanceSteps = $instance->instance_steps()
        ->with('workflowStep')
        ->orderBy('position', 'asc')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | 2. Identifier le début de la régularisation
    |--------------------------------------------------------------------------
    */

    $regularizationStart = $instanceSteps->first(
        function ($instanceStep) {
            return $instanceStep->workflowStep
                && $instanceStep->workflowStep->is_regularization_start;
        }
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Aucun début de régularisation défini
    |--------------------------------------------------------------------------
    |
    | Exemples :
    | - Papier taxi
    | - Notes de frais
    |
    | Le retour se fera au début du workflow.
    | Le frontend ne doit proposer aucune étape.
    |
    */

    if (!$regularizationStart) {
        return collect();
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Vérifier si la régularisation a été atteinte
    |--------------------------------------------------------------------------
    |
    | Tant que l'étape courante précède le début de la régularisation,
    | le retour se fait automatiquement au début du workflow.
    |
    */

    if (
        (int) $currentStep->position
        < (int) $regularizationStart->position
    ) {
        return collect();
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Proposer les destinations autorisées
    |--------------------------------------------------------------------------
    |
    | Conditions :
    | - étape terminée (COMPLETE) ;
    | - position égale ou supérieure au début de la régularisation ;
    | - position strictement inférieure à celle de l'étape courante.
    |
    */

    return $instanceSteps
        ->filter(function ($step) use (
            $regularizationStart,
            $currentStep
        ) {
            return $step->status === 'COMPLETE'
                && (int) $step->position
                    >= (int) $regularizationStart->position
                && (int) $step->position
                    < (int) $currentStep->position;
        })
        ->sortBy('position')
        ->values();
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
          $user = request()->get('user');

    // return
    $responsibilities =
    $user['employeeContext']['responsibilities'] ?? [];

    $isSuperAdmin = $this->responsibilityService->hasAnyCode(
    $responsibilities,
    [
        'SUPER_ADMIN',
        'DOCUMENT_ADMIN',
    ]
); 

    if ($isSuperAdmin) {
        
        return true;
    
    }

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

    if (!$messageBuilder) {
       
    return;
        
    }
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

    



    

}
