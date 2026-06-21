<?php

namespace App\Contracts;

interface WorkflowEventHandlerInterface
{
    public function execute(
        int $documentId,
        $instance,
        array $documentData,
        array $config = []
    ): array;
}