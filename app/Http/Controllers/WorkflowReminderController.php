<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStatusLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkflowReminderController extends Controller
{
    /**
     * Retourne les documents dont l'étape courante est PENDING
     * et dont le workflow_status_label correspond au status_code demandé.
     *
     * Logique :
     *
     * WorkflowInstance
     *      ↓
     * WorkflowInstanceStep PENDING
     *      ↓
     * workflow_status_label_id ?
     *      ↓
     * WorkflowStep.workflow_status_label_id
     *      ↓
     * WorkflowStatusLabel.code
     */
    public function getDocumentsByStatus(Request $request)
    {
        $request->validate([
            'status_code' => [
                'required',
                'string',
                'max:100',
            ],

            'document_type_id' => [
                'nullable',
                'integer',
            ],
        ]);

        // throw new \Exception(json_encode($request->get('status_code')), 1);
        

        $statusCode = strtoupper(
            trim($request->get('status_code'))
        );

        $documentTypeId = $request->get('document_type_id');

        Log::info('Reminder workflow status search started', [
            'status_code' => $statusCode,
            'document_type_id' => $documentTypeId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. Vérifier que le label existe
        |--------------------------------------------------------------------------
        */

        $statusLabel = WorkflowStatusLabel::where(
            'code',
            $statusCode
        )->first();

        if (!$statusLabel) {

            Log::warning(
                'Reminder workflow status label not found',
                [
                    'status_code' => $statusCode,
                    'document_type_id' => $documentTypeId,
                ]
            );

            return response()->json([
                'message' => 'Workflow status label not found.',

                'status' => [
                    'code' => $statusCode,
                ],

                'count' => 0,

                'data' => [],
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Chercher les WorkflowInstanceStep PENDING
        |--------------------------------------------------------------------------
        |
        | On ne cherche PAS directement les WorkflowInstance.
        |
        | La source de vérité pour la relance est l'étape PENDING.
        |
        */

        $instanceStepsQuery = WorkflowInstanceStep::query()
            ->where('status', 'PENDING')
            ->whereHas('workflowInstance', function ($query) use ($documentTypeId) {

                $query->whereNotNull('document_id');

                if (!empty($documentTypeId)) {
                    $query->where(
                        'document_type_id',
                        $documentTypeId
                    );
                }
            })
            ->with([
                'workflowInstance',
                'workflowStep',
            ]);

        $instanceSteps = $instanceStepsQuery
            ->orderBy('position', 'asc')
            ->get();

        Log::info(
            'Pending workflow instance steps found',
            [
                'status_code' => $statusCode,
                'status_label_id' => $statusLabel->id,
                'document_type_id' => $documentTypeId,
                'count' => $instanceSteps->count(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Déterminer le workflow_status_label_id effectif
        |--------------------------------------------------------------------------
        |
        | Priorité :
        |
        | A. WorkflowInstanceStep.workflow_status_label_id
        |
        | B. WorkflowStep.workflow_status_label_id
        |
        */

        $documents = collect();

        foreach ($instanceSteps as $instanceStep) {

            $instance = $instanceStep->workflowInstance;

            if (!$instance) {

                Log::warning(
                    'Pending instance step has no workflow instance',
                    [
                        'instance_step_id' => $instanceStep->id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | A. Label directement présent sur l'instance step
            |--------------------------------------------------------------------------
            */

            $workflowStatusLabelId =
                $instanceStep->workflow_status_label_id;

            $labelSource = 'INSTANCE_STEP';

            /*
            |--------------------------------------------------------------------------
            | B. Sinon, récupérer celui du WorkflowStep
            |--------------------------------------------------------------------------
            */

            if (!$workflowStatusLabelId) {

                if (!$instanceStep->workflowStep) {

                    Log::warning(
                        'Pending instance step has no workflow step',
                        [
                            'instance_step_id' => $instanceStep->id,
                            'workflow_instance_id' => $instance->id,
                            'workflow_step_id' => $instanceStep->workflow_step_id,
                        ]
                    );

                    continue;
                }

                $workflowStatusLabelId =
                    $instanceStep->workflowStep->workflow_status_label_id;

                $labelSource = 'WORKFLOW_STEP';
            }

            /*
            |--------------------------------------------------------------------------
            | Aucun label trouvé
            |--------------------------------------------------------------------------
            */

            if (!$workflowStatusLabelId) {

                Log::warning(
                    'Pending workflow step has no workflow status label',
                    [
                        'instance_step_id' => $instanceStep->id,
                        'workflow_instance_id' => $instance->id,
                        'workflow_step_id' => $instanceStep->workflow_step_id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Vérifier que le label correspond au code recherché
            |--------------------------------------------------------------------------
            */

            if ((int) $workflowStatusLabelId !== (int) $statusLabel->id) {

                Log::debug(
                    'Pending instance step ignored because status label does not match',
                    [
                        'instance_step_id' => $instanceStep->id,
                        'workflow_instance_id' => $instance->id,
                        'expected_status_code' => $statusCode,
                        'expected_status_label_id' => $statusLabel->id,
                        'actual_status_label_id' => $workflowStatusLabelId,
                        'label_source' => $labelSource,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Déterminer le status_started_at
            |--------------------------------------------------------------------------
            |
            | Pour le Reminder Engine, le temps doit correspondre au début
            | de l'étape PENDING.
            |
            | Ton WorkflowInstanceStep ne montre pas de started_at.
            |
            | On utilise donc created_at comme fallback.
            |
            */

            // $statusStartedAt = $instanceStep->created_at;

                        $previousInstanceStep = WorkflowInstanceStep::query()
                ->where('workflow_instance_id', $instance->id)
                ->where('position', '<', $instanceStep->position)
                ->whereNotNull('executed_at')
                ->orderByDesc('position')
                ->first();

            $statusStartedAt = null;

            if ($previousInstanceStep) {
                $statusStartedAt = $previousInstanceStep->executed_at;

                // throw new \Exception($statusStartedAt, 1);
                
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Ajouter le document
            |--------------------------------------------------------------------------
            */

            $documents->push([
                'document_id' => $instance->document_id,

                'document_uuid' => $instance->document_uuid,

                'document_type_id' => $instance->document_type_id,

                'document_type_relation_name' =>
                    $instance->document_type_relation_name,

                'workflow_instance_id' => $instance->id,

                'instance_step_id' => $instanceStep->id,

                'workflow_step_id' =>
                    $instanceStep->workflow_step_id,

                'status_code' =>
                    $statusLabel->code,

                'status_label' =>
                    $statusLabel->label,

                'workflow_status_label_id' =>
                    $workflowStatusLabelId,

                'status_label_source' =>
                    $labelSource,

                'status_started_at' =>
                    $statusStartedAt,

                'step_status' =>
                    $instanceStep->status,

                'step_position' =>
                    $instanceStep->position,

                'step_name' =>
                    $instanceStep->workflowStep
                        ? $instanceStep->workflowStep->name
                        : null,

                'role_id' =>
                    $instanceStep->role_id,

                'user_id' =>
                    $instanceStep->user_id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Éviter les doublons de documents
        |--------------------------------------------------------------------------
        |
        | Une instance peut théoriquement avoir plusieurs instance_steps
        | PENDING.
        |
        | Pour le Reminder Engine, on ne veut qu'une entrée par workflow
        | instance.
        |
        */

        $documents = $documents
            ->unique('workflow_instance_id')
            ->values();

        Log::info(
            'Reminder workflow documents resolved',
            [
                'status_code' => $statusCode,
                'status_label_id' => $statusLabel->id,
                'document_type_id' => $documentTypeId,
                'count' => $documents->count(),
            ]
        );

        return response()->json([
            'status' => [
                'id' => $statusLabel->id,
                'code' => $statusLabel->code,
                'label' => $statusLabel->label,
                'emoji' => $statusLabel->emoji,
                'color' => $statusLabel->color,
                'status_type' => $statusLabel->status_type,
            ],

            'filters' => [
                'status_code' => $statusCode,
                'document_type_id' => $documentTypeId,
            ],

            'count' => $documents->count(),

            'data' => $documents,
        ]);
    }
}