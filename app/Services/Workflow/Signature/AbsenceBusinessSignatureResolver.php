<?php

namespace App\Services\Workflow\Signature;

use App\Models\Signature;

class AbsenceBusinessSignatureResolver
    implements BusinessSignatureResolver
{
    public function resolve(int $documentId): array
    {
        // throw new \Exception("ICI", 1);
        
        return  [];
        
        Signature::query()
            ->with('signatureType')
            ->where('document_id', $documentId)
            ->whereHas('signatureType', function ($q) {
                $q->where('code', 'REGULARIZATION_ADVANCE');
            })
            ->get()
            ->toArray();
    }
}