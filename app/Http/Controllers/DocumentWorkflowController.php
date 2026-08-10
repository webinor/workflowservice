<?php

namespace App\Http\Controllers;

use App\Models\WorkflowActionStep;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocumentWorkflowController extends Controller
{
    public function previewHistory(Request $request, $documentId)
    {
        // Récupérer l’historique des étapes validées
        $historyData = $this->validationHistory($request, $documentId);

        $history = $historyData->getData(true); // true pour obtenir un array
        // maintenant tu peux faire :
        $historyArray = $history["data"];

        return Pdf::loadView("workflow.history_preview", [
            "history" => $historyArray,
        ])->stream("preview.pdf");
    }

    /**
     * Historique des validations d'un document
     */
    public function validationHistory(Request $request, $documentId)
    {
        // 🔹 Récupère les actions liées au document
        $history = WorkflowInstanceStep::with([
            "workflowStep",
            "workflowStep.workflowActionSteps.workflowAction",
        ])
            ->where("workflow_instance_id", function ($q) use ($documentId) {
                $q->select("id")
                    ->from("workflow_instances")
                    ->where("document_id", $documentId);
            })
            ->where("status", "COMPLETE")
            ->orderBy("executed_at", "asc")
            ->get();

        // 2️⃣ Transformer le résultat
        $result = $history->map(function ($step) use ($request) {
            // Nom de l'action associée à cette étape
            $actionName = "Action inconnue";
            $actionLabel = "Label inconnue";

            if (
                $step->workflowStep &&
                $step->workflowStep->workflowActionSteps &&
                $step->workflowStep->workflowActionSteps->first() &&
                $step->workflowStep->workflowActionSteps->first()
                    ->workflowAction
            ) {
                $actionName = $step->workflowStep->workflowActionSteps->first()
                    ->workflowAction->name;
                $actionLabel = $step->workflowStep->workflowActionSteps->first()
                    ->workflowAction->action_label;
            }

            // Appel microservice utilisateur pour récupérer le nom
            $userName = null;
            if ($step->user_id) {
                try {
                    $response = Http::withToken($request->bearerToken())->get(
                        config("services.user_service.base_url") .
                            "/{$step->user_id}"
                    );
                    if ($response->successful()) {
                        $userData = $response->json()["user"];
                        $userName = $userData["name"] ?? null;
                    }
                } catch (\Throwable $e) {
                    $userName = "Utilisateur #{$step->user_id}";
                }
            }

            return [
                "step_name" => $step->workflowStep->name,
                "action_name" => $actionName,
                "action_label" => $actionLabel,
                "user" => $userName,
                "executed_at" => $step->executed_at,
                "status" => $step->status,
            ];
        });

        return response()->json([
            "success" => true,
            "data" => $result,
        ]);
    }

    /**
     * Retourne l'historique des validations pour un document
     */
    public function old_validationHistory($documentId, Request $request)
    {
        // Récupérer l'instance de workflow du document
        $instance = WorkflowInstance::where(
            "document_id",
            $documentId
        )->first();

        if (!$instance) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Aucune instance de workflow trouvée pour ce document.",
                ],
                404
            );
        }

        // Récupérer les étapes complétées
        $steps = $instance
            ->instance_steps()
            ->whereNotNull("executed_at")
            ->orderBy("executed_at", "asc")
            ->get();

        $history = [];

        foreach ($steps as $step) {
            // Appel microservice utilisateur pour enrichir l'ID en nom
            $userName = $this->getUserName(
                $step->user_id,
                $request->bearerToken()
            );

            $history[] = [
                "step_name" => $step->workflowStep->name ?? "Inconnu",
                "action" => $this->getActionLabel($step),
                "user" => $userName,
                "executed_at" => $step->executed_at,
            ];
        }

        return response()->json([
            "success" => true,
            "data" => $history,
        ]);
    }

    /**
     * Retourne un label lisible pour l'action effectuée
     */
    protected function getActionLabel($step)
    {
        switch ($step->status) {
            case "SUBMITTED":
                return "soumis";
            case "RECOGNIZED":
                return "reconnue";
            case "VERIFIED":
                return "vérifiée";
            case "SIGNED":
                return "signée";
            default:
                return "action inconnue";
        }
    }

    /**
     * Récupère le nom de l'utilisateur depuis le microservice Users
     */
    protected function getUserName($userId, $token)
    {
        if (!$userId) {
            return "Inconnu";
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(config("services.user_service.base_url") . "/{$userId}");

        if ($response->successful()) {
            $userData = $response->json();
            return $userData["user"]["name"] ?? "Inconnu";
        }

        return "Inconnu";
    }
}
