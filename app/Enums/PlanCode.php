<?php

namespace App\Enums;

enum PlanCode: string
{
    case Basic = 'basic';
    case Premium = 'premium';
    case Ultra = 'ultra';
    case Custom = 'custom';

    /**
     * Get the display label for the plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic',
            self::Premium => 'Premium',
            self::Ultra => 'Ultra',
            self::Custom => 'Custom',
        };
    }
}
