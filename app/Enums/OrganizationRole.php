<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
