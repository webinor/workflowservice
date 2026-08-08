<?php

namespace App\Services\Visibility;

use Illuminate\Database\Eloquent\Builder;

interface VisibilityPolicyInterface
{
    public function apply(
        Builder $query,
        int $roleId,
        int $userId,
        int $employeeId,
        array $responsibilities = []
    ): Builder;
}