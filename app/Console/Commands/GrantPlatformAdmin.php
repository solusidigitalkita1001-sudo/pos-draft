<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform-admin:grant {email}')]
#[Description('Grant a user access to the cross-tenant admin panel (e.g. to review custom plan requests).')]
class GrantPlatformAdmin extends Command
{
    /**
     * `is_platform_admin` is deliberately excluded from User's
     * #[Fillable(...)] list — it must never be settable via mass
     * assignment from an HTTP request. This command uses forceFill()
     * as the one intentional, explicit way to set it.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User dengan email {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->info("{$user->email} sekarang punya akses admin panel.");

        return self::SUCCESS;
    }
}
