<?php

namespace App\Contracts;


interface WorkflowNotificationMessageBuilder
{
    public function build(array $document): array;
}