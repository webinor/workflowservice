<?php

namespace App\Services\Mission;


use Exception;

 class MissionEnricher{


 public function enrich(array $doc, ?array $ctx): array
{
    
    
    $hasExpenceAdvanceSignature = collect($ctx['signatures'] ?? [])
        ->contains(fn ($s) =>
            $s['code'] === 'MISSION_EXPENSE_ADVANCE'
            && $s['signed']
        );

    $hasSettlementSignature = collect($ctx['signatures'] ?? [])
        ->contains(fn ($s) =>
            $s['code'] === 'MISSION_SETTLEMENT'
            && $s['signed']
        );

    // throw new Exception(json_encode($hasSignature), 1);

    $workflowCompleted =   ($ctx['workflow_status'] ?? null) === 'COMPLETE';

    $doc['actions'] = [
        [
            "code" => "DOWNLOAD",
            // "enabled" =>$hasSignature,// $workflowCompleted && $hasSignature,
            // "reason" => $hasSignature ? null : "Signature bénéficiaire requise",
        ],
        [
            "code" => "EXPENSE_ADVANCE_SIGN",
            "enabled" => !$hasExpenceAdvanceSignature,
        ],

        [
            "code" => "MISSION_SETTLEMENT_SIGN",
            "enabled" => !$hasSettlementSignature,
        ],
    ];

    $doc['availability'] = [
        "can_download" =>$hasExpenceAdvanceSignature && $hasSettlementSignature ,// $workflowCompleted && $hasSignature,
        "can_sign_expense_advance" => !$hasExpenceAdvanceSignature,
        "can_sign_settlement" => !$hasSettlementSignature,
    ];

    return $doc;
}


}

