<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowInstanceStepRequest;
use App\Http\Requests\UpdateWorkflowInstanceStepRequest;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WorkflowInstanceStepController extends Controller
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

    private function formatHistoryComment($history): string
{
    switch ($history->new_status) {

        case 'RETURNED_FOR_MODIFICATION':
            return 'Motif de retour : ' . $history->comment;

        case 'REJECTED':
            return 'Motif de rejet : ' . $history->comment;

        case 'CANCELLED':
            return "Motif d'annulation : " . $history->comment;

        case 'COMPLETE':
            return "Approuvé : " . $history->comment;

        default:
            // return $history->new_status . ' : ' . $history->comment;
            return $history->comment;
    }
}

    public function getWorkflowComments(Request $request, $documentIdentifier)
{
    // 1. Récupérer l'instance du workflow
    if (Str::isUuid($documentIdentifier)) {
        $workflowInstance = WorkflowInstance::whereDocumentUuid(
            $documentIdentifier
        )->first();
    } else {
        $workflowInstance = WorkflowInstance::whereDocumentId(
            $documentIdentifier
        )->first();
    }

    if (!$workflowInstance) {
        return response()->json([
            "success" => false,
            "message" => "Workflow instance introuvable.",
        ], 404);
    }

    // 2. Charger les histories de l'instance
    //    et celles de tous ses instance_steps
    $workflowInstance->load([
        "histories",
        "instance_steps.histories",
    ]);

    // 3. Construire une timeline unique
    $histories = collect();

    /*
    |--------------------------------------------------------------------------
    | Histories directement liées au WorkflowInstance
    |--------------------------------------------------------------------------
    |
    | Exemple :
    | WorkflowInstance -> CANCELLED
    |
    */
    $instanceHistories = $workflowInstance->histories
        ->filter(function ($history) {
            return !empty($history->comment);
        })
        ->map(function ($history) {

            return [
                "workflow_step_id" => null,
                "workflow_instance_step_id" => null,
                "changed_by" => $history->changed_by,
                "old_status" => $history->old_status,
                "new_status" => $history->new_status,
                "comment" => $this->formatHistoryComment($history),
                "created_at" => $history->created_at,
            ];
        });

    $histories = $histories->merge($instanceHistories);

    /*
    |--------------------------------------------------------------------------
    | Histories liées aux WorkflowInstanceStep
    |--------------------------------------------------------------------------
    |
    | Exemple :
    | WorkflowInstanceStep -> BYPASSED
    | WorkflowInstanceStep -> RETURNED
    | WorkflowInstanceStep -> REJECTED
    |
    */
    $stepHistories = $workflowInstance->instance_steps
        ->flatMap(function ($step) {

            return $step->histories
                ->filter(function ($history) {
                    return !empty($history->comment);
                })
                ->map(function ($history) use ($step) {

                    return [
                        "workflow_step_id" => $step->workflow_step_id,
                        "workflow_instance_step_id" => $step->id,
                        "changed_by" => $history->changed_by,
                        "old_status" => $history->old_status,
                        "new_status" => $history->new_status,
                        "comment" => $this->formatHistoryComment($history),
                        "created_at" => $history->created_at,
                    ];
                });
        });

    $histories = $histories->merge($stepHistories);

    /*
    |--------------------------------------------------------------------------
    | Trier chronologiquement
    |--------------------------------------------------------------------------
    |
    | IMPORTANT :
    | On trie alors que created_at est encore un objet Carbon.
    | On ne fait pas le sortBy() après avoir fait format("d/m/Y H:i").
    |
    */
    $histories = $histories
        ->sortBy(function ($history) {
            return $history["created_at"];
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Récupérer les utilisateurs
    |--------------------------------------------------------------------------
    |
    | On évite de faire plusieurs appels pour le même utilisateur.
    |
    */
    $userIds = $histories
        ->pluck("changed_by")
        ->filter()
        ->unique()
        ->values();

    $users = collect();

    foreach ($userIds as $userId) {

        $response = Http::acceptJson()
            ->withToken($request->bearerToken())
            ->get(
                config("services.user_service.base_url") .
                "/{$userId}"
            );

        if ($response->successful()) {

            $user = $response->json()["user"] ?? null;

            if ($user) {
                $users->put($userId, $user);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Construire la réponse finale
    |--------------------------------------------------------------------------
    */
    $histories = $histories
        ->map(function ($history) use ($users) {

            $userData = $history["changed_by"]
                ? $users->get($history["changed_by"])
                : null;

            return [
                "workflow_step_id" =>
                    $history["workflow_step_id"],

                "workflow_instance_step_id" =>
                    $history["workflow_instance_step_id"],

                "changed_by" =>
                    $history["changed_by"],

                "user_name" =>
                    $userData["name"] ?? "Utilisateur inconnu",

                "old_status" =>
                    $history["old_status"],

                "new_status" =>
                    $history["new_status"],

                "comment" =>
                    $history["comment"],

                "created_at" =>
                    $history["created_at"]->format("d/m/Y H:i"),
            ];
        })
        ->toArray();

    return response()->json([
        "success" => true,
        "workflow_instance_id" => $workflowInstance->id,
        "history" => $histories,
    ]);
}

    public function OldgetWorkflowComments(Request $request, $documentIdentifier)
    {

     if (Str::isUuid($documentIdentifier)) {


     $workflowInstance = WorkflowInstance::whereDocumentUuid(
            $documentIdentifier
        )->first();

     
} else {
    

$workflowInstance = WorkflowInstance::whereDocumentId(
            $documentIdentifier
        )->first();

     


}


   $steps = WorkflowInstanceStep::whereWorkflowInstanceId(
            $workflowInstance->id
        )
            ->whereHas("histories", function ($query) {
                $query->whereNotNull("comment");
            })
            ->with("histories")
            ->orderBy("created_at", "asc")
            ->get();


            


        
        // FlatMap pour obtenir un tableau plat de toutes les histories
       $histories = $steps
    ->flatMap(function ($step) use ($request) {
        return $step->histories
            ->filter(function ($history) {
                // On ne garde que les historiques avec un commentaire non vide
                return !empty($history->comment);
            })
            ->map(function ($history) use ($step, $request) {
                // Appel microservice User pour récupérer l'utilisateur qui a fait le changement
                $userData = null;
                if ($history->changed_by) {
                    $response = Http::acceptJson()
                        ->withToken($request->bearerToken())
                        ->get(
                            config("services.user_service.base_url") .
                                "/{$history->changed_by}"
                        );

                    $userData = $response->successful()
                        ? $response->json()["user"]
                        : null;
                }

                return [
                    "workflow_step_id" => $step->workflow_step_id,
                    "workflow_instance_step_id" => $step->id,
                    "changed_by" => $history->changed_by,
                    "user_name" =>
                        $userData["name"] ?? "Utilisateur inconnu",
                    "old_status" => $history->old_status,
                    "new_status" => $history->new_status,
                    "comment" => $history->comment,
                    "created_at" => $history->created_at->format(
                        "d/m/Y H:i"
                    ),
                ];
            });
    })
    ->sortBy("created_at")
    ->values()
    ->toArray();


        return response()->json([
            "success" => true,
            "workflow_instance_id" => $workflowInstance->id,
            "history" => $histories,
        ]);
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
     * @param  \App\Http\Requests\StoreWorkflowInstanceStepRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowInstanceStepRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowInstanceStepRequest  $request
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateWorkflowInstanceStepRequest $request,
        WorkflowInstanceStep $workflowInstanceStep
    ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowInstanceStep  $workflowInstanceStep
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowInstanceStep $workflowInstanceStep)
    {
        //
    }
}
