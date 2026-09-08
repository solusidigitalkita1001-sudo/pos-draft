<?php

namespace App\Actions\Fortify;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\PlanCode;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use RuntimeException;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private CreateTeam $createTeam,
        private CreateOrganizationAction $createOrganization,
    ) {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            // Setiap user baru langsung dapat 1 organization (akun
            // berlangganan) di plan Basic dengan masa trial, supaya
            // langsung bisa dipakai tanpa perlu proses checkout dulu.
            $basicPlan = Plan::findByCode(PlanCode::Basic);

            if (! $basicPlan) {
                throw new RuntimeException('Plan "basic" belum ada — jalankan PlanSeeder terlebih dahulu.');
            }

            $organization = $this->createOrganization->execute(
                user: $user,
                name: $user->name."'s Organization",
                plan: $basicPlan,
            );

            $this->createTeam->handle(
                user: $user,
                name: $user->name."'s Team",
                organization: $organization,
                isPersonal: true,
            );

            return $user;
        });
    }
}
