<?php
namespace App\Services;

use App\Models\DocumentTypeWorkflow;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStepRole;
use Exception;
use Illuminate\Support\Facades\Http;

class WorkflowInstanceService
{
    use ResolveDepartmentValidator;

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

        // 🔎 Récupérer les infos du document depuis DocumentService
        //return

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

            // throw new Exception(json_encode($documentData), 1);





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

                // throw new Exception(json_encode($response->successful()), 1);

                // Récupérer juste les IDs
                $userIds = collect($users)
                    ->pluck("id")
                    ->toArray();

                // Notifier en une seule requête
                return Http::withToken($request->bearerToken())->post(
                    config("services.user_service.base_url") . "/notifications",
                    [
                        "user_ids" => $userIds,
                        "payload" => $payload,
                        "document_id" => $documentId,
                        "document_type_id" => $documentTypeId,
                    ]
                );
            }
        }
    }
}
