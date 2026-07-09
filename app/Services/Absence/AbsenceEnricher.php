<?php

namespace App\Services\Absence;


use Exception;

 class AbsenceEnricher{


 public function enrich(array $doc, ?array $ctx): array
{
    
    
    $hasSignature = collect($ctx['signatures'] ?? [])
        ->contains(fn ($s) =>
            $s['code'] === 'TAXI_PAPER_SETTLEMENT'
            && $s['signed']
        );

    // throw new Exception(json_encode($hasSignature), 1);

    $workflowCompleted =   ($ctx['workflow_status'] ?? null) === 'COMPLETE';

    $doc['actions'] = [
        [
            "code" => "DOWNLOAD",
            "enabled" =>$hasSignature,// $workflowCompleted && $hasSignature,
            "reason" => $hasSignature ? null : "Signature bénéficiaire requise",
        ],
        [
            "code" => "SIGN",
            "enabled" => !$hasSignature,
        ],
    ];

    $doc['availability'] = [
        "can_download" =>$hasSignature,// $workflowCompleted && $hasSignature,
        "can_sign" => !$hasSignature,
    ];

    return $doc;
}


}

