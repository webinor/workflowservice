<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowSearchController extends Controller
{

    public function workflowDelayDocuments(Request $request)
{
    $request->validate([
        'delay' => [
            'required',
            'in:less_than_2h,2_6h,6_24h,1_2_days,more_than_2_days'
        ]
    ]);


    $delay = $request->input('delay');


    $now = now();


    $query = WorkflowInstance::query()
        ->join(
            'workflow_instance_steps',
            'workflow_instances.id',
            '=',
            'workflow_instance_steps.workflow_instance_id'
        )
        ->whereNull(
            // 'workflow_instance_steps.completed_at'
            'workflow_instance_steps.executed_at'

        );


    switch ($delay) {


        case 'less_than_2h':

            $query->whereRaw(
                'TIMESTAMPDIFF(
                    HOUR,
                    workflow_instance_steps.created_at,
                    ?
                ) < 2',
                [
                    $now
                ]
            );

        break;



        case '2_6h':

            $query->whereRaw(
                'TIMESTAMPDIFF(
                    HOUR,
                    workflow_instance_steps.created_at,
                    ?
                ) BETWEEN 2 AND 6',
                [
                    $now
                ]
            );

        break;



        case '6_24h':

            $query->whereRaw(
                'TIMESTAMPDIFF(
                    HOUR,
                    workflow_instance_steps.created_at,
                    ?
                ) BETWEEN 6 AND 24',
                [
                    $now
                ]
            );

        break;



        case '1_2_days':

            $query->whereRaw(
                'TIMESTAMPDIFF(
                    HOUR,
                    workflow_instance_steps.created_at,
                    ?
                ) BETWEEN 24 AND 48',
                [
                    $now
                ]
            );

        break;



        case 'more_than_2_days':

            $query->whereRaw(
                'TIMESTAMPDIFF(
                    HOUR,
                    workflow_instance_steps.created_at,
                    ?
                ) > 48',
                [
                    $now
                ]
            );

        break;

    }


    $documentIds = $query
        ->pluck('workflow_instances.document_id')
        ->unique()
        ->values();


    return response()->json([
        "success" => true,
        "data" => $documentIds
    ]);
}

}