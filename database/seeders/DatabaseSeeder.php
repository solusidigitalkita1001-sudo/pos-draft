<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Menu;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ProductPromotionPermissionSeeder::class,
            MenuSeeder::class,
            PlanSeeder::class,
            UserSeeder::class,
        ]);
    }
}

// ──────────────────────────────────────────────────────
// FILE: database/seeders/PermissionSeeder.php
// ──────────────────────────────────────────────────────
// Defines all system permissions grouped by module.
// Roles are created per-team by owners, not globally.