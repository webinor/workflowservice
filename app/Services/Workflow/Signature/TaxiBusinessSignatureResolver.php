<?php

namespace App\Services\Workflow\Signature;

use App\Models\Signature;

class TaxiBusinessSignatureResolver
    implements BusinessSignatureResolver
{
    public function resolve(int $documentId): array
    {
        return Signature::query()
            ->with('signatureType')
            ->where('document_id', $documentId)
            ->whereHas('signatureType', function ($q) {
                $q->where('code', 'TAXI_PAPER_SETTLEMENT');
            })
            ->get()
            ->toArray();
    }
}