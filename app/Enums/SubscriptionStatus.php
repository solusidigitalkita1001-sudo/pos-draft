<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Aktif',
            self::PastDue => 'Menunggak',
            self::Suspended => 'Ditangguhkan',
            self::Canceled => 'Dibatalkan',
        };
    }

    /**
     * Determine if an organization on this status should still be able
     * to use the system normally (create transactions, etc).
     */
    public function isUsable(): bool
    {
        return match ($this) {
            self::Trial, self::Active, self::PastDue => true,
            self::Suspended, self::Canceled => false,
        };
    }
}
