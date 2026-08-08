<?php

namespace App\Services\Visibility;

use App\Services\Visibility\VisibilityPolicyResolver;
use Illuminate\Database\Eloquent\Builder;

class DocumentVisibilityService
{
    protected VisibilityPolicyResolver $resolver;

    public function __construct(
        VisibilityPolicyResolver $resolver
    ) {
        $this->resolver = $resolver;
    }

    public function apply(
        Builder $query,
        string $documentType,
        int $roleId,
        int $userId,
        int $employeeId,
        array $responsibilities = []
    ): Builder {

        $policy = $this->resolver->resolve(
            $documentType
        );

        return $policy->apply(
            $query,
            $roleId,
            $userId,
            $employeeId,
            $responsibilities
        );
    }
}