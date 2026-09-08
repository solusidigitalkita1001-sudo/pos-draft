<?php

namespace App\Exceptions;

use App\Models\Organization;
use RuntimeException;

class StoreQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly Organization $organization,
        public readonly ?int $limit,
    ) {
        $limitLabel = $limit === null ? 'unlimited' : (string) $limit;

        parent::__construct("Store quota exceeded for organization #{$organization->id} (limit: {$limitLabel}).");
    }
}
