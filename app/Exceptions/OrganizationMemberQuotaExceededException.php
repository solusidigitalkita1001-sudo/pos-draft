<?php

namespace App\Exceptions;

use App\Models\Organization;
use RuntimeException;

class OrganizationMemberQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly Organization $organization,
        public readonly ?int $limit,
    ) {
        $limitLabel = $limit === null ? 'unlimited' : (string) $limit;

        parent::__construct("Owner/manager quota exceeded for organization #{$organization->id} (limit: {$limitLabel}).");
    }
}
