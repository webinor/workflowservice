<?php

namespace App\Contracts;


interface WorkflowAmountResolver
{
    public function amount(array $document): float;
}