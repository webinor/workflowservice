<?php

namespace App\Contracts;

interface WorkflowEventHandlerInterface
{
    public function execute(
        string $documentUuid,
        $instance,
        array $documentData,
        array $config = []
    ): array;
}