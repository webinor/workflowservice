<?php

namespace App\Services\FeeNote;


use Exception;

 class FeeNoteEnricher{


 public function enrich(array $doc, ?array $ctx): array
{
    
    
    $hasSignature = collect($ctx['signatures'] ?? [])
        ->contains(fn ($s) =>
            $s['code'] === 'FEE_NOTE_SETTLEMENT'
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

