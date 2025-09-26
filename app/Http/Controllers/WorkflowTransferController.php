<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusHistory;
use Illuminate\Support\Facades\Http;

class WorkflowTransferController extends Controller
{
    public function transferDocument(Request $request)
    {
        $validated = $request->validate([
            "document_id" => "required|integer",
            "transfer_to" => "required|integer",
            "comment" => "required|string",
        ]);

        $user = $request->get("user");

        DB::beginTransaction();
        try {
            // 1️⃣ Récupérer l'instance du workflow du document
            $workflowInstance = WorkflowInstance::where(
                "document_id",
                $validated["document_id"]
            )->firstOrFail();

            // 2️⃣ Récupérer l'étape en cours
            $step = WorkflowInstanceStep::where(
                "workflow_instance_id",
                $workflowInstance->id
            )
                ->whereIn("status", ["PENDING", "IN_PROGRESS"]) // étapes actives
                ->orderBy("id", "asc") // la plus ancienne étape active
                ->firstOrFail();

            // 2️⃣ Appel au microservice department pour récupérer le responsable
            $deptResponse = Http::acceptJson()->get(
                config("services.department_service.base_url") .
                    "/{$validated["transfer_to"]}/hierarchie"
            );

            if ($deptResponse->failed()) {
                throw new \Exception(
                    "Impossible de récupérer le responsable du département."
                );
            }

            $responsable = $deptResponse->json(); // ex: ['user_id' => 12]

            // 3️⃣ Appel au microservice user pour récupérer le role du responsable
            $roleResponse = Http::acceptJson()->get(
                config("services.user_service.base_url") .
                    "/roles/search?name={$responsable["position"]["name"]}"
            );

            if ($roleResponse->failed()) {
                throw new \Exception(
                    "Impossible de récupérer le role du responsable."
                );
            }

            $role = $roleResponse->json(); // ex: ['role_id' => 3]

            $oldStatus = $step->status;
            // 🔹 Mise à jour de l'étape
            //$step->user_id       = $responsable['user_id'];
            $step->role_id = $role["results"]["id"];
            $step->status = "PENDING";
            $step->save();

            // 🔹 Historisation
            $history = WorkflowStatusHistory::create([
                "model_id" => $step->id,
                "model_type" => WorkflowInstanceStep::class,
                "changed_by" => $user["id"], // ?? null,
                "old_status" => $oldStatus,
                "new_status" => $step->status,
                "comment" => $validated["comment"] ?? null,
            ]);

            // 5️⃣ Ajouter le commentaire via microservice document
            /*if (!empty($validated['comment'])) {
                $commentResponse = Http::post('http://document-service/api/comments', [
                    'model_type' => 'document',
                    'model_id'   => $validated['document_id'],
                    'body'       => $validated['comment'],
                    'user_id'    => auth()->id() ?? null, // utilisateur qui effectue le transfert
                ]);

                if ($commentResponse->failed()) {
                    throw new \Exception('Impossible de sauvegarder le commentaire sur document-service.');
                }
            }*/

            /**
             *
             *
             * on notifie le role
             */

            DB::commit();

            return response()->json(
                [
                    "success" => true,
                    "message" => "Document transféré avec succès",
                    "step" => $step,
                    "history" => $history,
                ],
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(
                [
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }
}
