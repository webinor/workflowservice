<?php
namespace App\Services;

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
        'workflow_status_label_ido' => WorkflowStatusLabel::whereCode("RETURNED_FOR_MODIFICATION")->first()->id 

    ]);

    $this->resetAssignSteps($step);
}

private function resetAssignSteps(
    WorkflowInstanceStep $instanceStep
): void
{
    $data = $instanceStep->workflowStep->assignment_mode == "OWNER" ? ['decision' => "PENDING" , 'validated_at'=>null] : 
    [
        'user_id'=> null,
        'decision' => "PENDING",
         'validated_at'=>null
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


    public function notifyNextValidator(
        WorkflowInstanceStep $stepInstance,
        $request,
        $departmentId = "",
        $stepRoles = []
    ) {
        // $step = $stepInstance->load("workflowStep")->workflowStep;
        // $stepRoles = [];

        // Vérifier si l'étape est PENDING
        if ($stepInstance->status !== "PENDING") {
            ///  return;
        }


        $workflowInstance = $stepInstance->workflowInstance;
        $documentId = $workflowInstance->document_id;
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

        // throw new Exception(json_encode($response->body()), 1);

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

                // Notifier en une seule requête
                $notifyResponse = Http::withToken($request->bearerToken())->post(
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
