<?php

namespace App\Services\Workflow\Signature;

use App\Models\Signature;

class MissionBusinessSignatureResolver
    implements BusinessSignatureResolver
{
    public function resolve(int $documentId): array
    {
        // throw new \Exception("ICI", 1);
        
        return Signature::query()
            ->with('signatureType')
            ->where('document_id', $documentId)
            ->whereHas('signatureType', function ($q) {
                $q->where('code', 'MISSION_EXPENSE_ADVANCE');
            })
            ->get()
            ->toArray();
    }
}