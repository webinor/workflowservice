<?php

namespace App\Http\Controllers;

use App\Models\WorkflowEventAudience;
use App\Models\WorkflowInstanceStepAssignment;
use App\Models\WorkflowStep;

class RoleUsageController extends Controller
{
    public function workflowUsage(int $roleId)
    {
        /*
        |--------------------------------------------------------------------------
        | Définition des workflows
        |--------------------------------------------------------------------------
        */

        $steps = WorkflowStep::where('role_id', $roleId)->count();


        $audiences = WorkflowEventAudience::where('target_type', 'ROLE')
            ->where('target_value', $roleId)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Instances de workflow
        |--------------------------------------------------------------------------
        */

        $assignments = WorkflowInstanceStepAssignment::where('role_id', $roleId)
    ->whereIn('decision', [
        'PENDING',
        'IN_PROGRESS',
        'WAITING'
    ])
    ->count();

        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $count = $steps
            + $audiences
            + $assignments;

        return response()->json([
            'used' => $count > 0,
            'count' => $count,
            'details' => [
                'workflow_steps' => $steps,
                'workflow_event_audiences' => $audiences,
                'workflow_instance_step_assignments' => $assignments,
            ],
        ]);
    }
}