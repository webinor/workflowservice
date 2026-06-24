<?php


namespace App\Services\Workflow\Signature;

interface BusinessSignatureResolver
{
    public function resolve(int $documentId): array;
}