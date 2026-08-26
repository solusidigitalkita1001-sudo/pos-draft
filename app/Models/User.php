<?php

namespace App\Models;

use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;
    use HasTeams, HasRoles {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as teamsFromHasRoles;
    }

    /**
     * Get all accessible menus for the user on their current team.
     * Owner bypasses most permission checks.
     *
     * @return \Illuminate\Support\Collection<int, Menu>
     */
    public function accessibleMenus(): \Illuminate\Support\Collection
    {
        $team = $this->currentTeam;

        if (! $team) {
            return collect();
        }

        $isDeveloper = $this->hasDeveloperRole();

        // Owner bypasses — gets all active root menus with children
        if ($this->ownsTeam($team)) {
            return Menu::with('children.permissions')
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function (Menu $menu) use ($isDeveloper) {
                    $menu->setRelation(
                        'children',
                        $menu->children
                            ->reject(fn (Menu $child) => ! $isDeveloper && $this->isDeveloperOnlyMenu($child))
                            ->values(),
                    );

                    return $menu;
                })
                ->reject(fn (Menu $menu) => ! $isDeveloper && $this->isDeveloperOnlyMenu($menu))
                ->filter(fn (Menu $menu) => $menu->children->isNotEmpty() || $menu->route !== null)
                ->values();
        }

        // Get all permissions for this user on this team
        setPermissionsTeamId($team->id);
        $userPermissions = $this->getAllPermissions()->pluck('name');

        // Get menus accessible by those permissions
        return Menu::with(['children' => function ($query) use ($userPermissions) {
            $query->where('is_active', true)
                ->whereHas('permissions', fn ($q) => $q->whereIn('name', $userPermissions))
                ->orderBy('sort_order');
        }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', $userPermissions))
            ->orWhereDoesntHave('permissions') // menus with no permission req. are public
            ->orderBy('sort_order')
            ->get()
            ->map(function (Menu $menu) use ($isDeveloper) {
                $menu->setRelation(
                    'children',
                    $menu->children
                        ->reject(fn (Menu $child) => ! $isDeveloper && $this->isDeveloperOnlyMenu($child))
                        ->values(),
                );

                return $menu;
            })
            ->reject(fn (Menu $menu) => ! $isDeveloper && $this->isDeveloperOnlyMenu($menu))
            ->filter(fn ($menu) => $menu->children->isNotEmpty() || $menu->route !== null)
            ->values();
    }

    /**
     * Check if user has a specific spatie permission on their current team.
     */
    public function canOnCurrentTeam(string $permission): bool
    {
        $team = $this->currentTeam;
        if (! $team) {
            return false;
        }

        if ($this->isDeveloperOnlyPermission($permission)) {
            return $this->hasDeveloperRole();
        }

        if ($this->ownsTeam($team)) {
            return true;
        }

        setPermissionsTeamId($team->id);

        try {
            return $this->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function hasDeveloperRole(): bool
    {
        $roleTable = config('permission.table_names.roles');
        $modelRoleTable = config('permission.table_names.model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key') ?: 'team_id';
        $modelKey = config('permission.column_names.model_morph_key') ?: 'model_id';
        $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        return DB::table($modelRoleTable)
            ->join($roleTable, "{$roleTable}.id", '=', "{$modelRoleTable}.{$rolePivotKey}")
            ->where("{$modelRoleTable}.{$modelKey}", $this->getKey())
            ->where("{$modelRoleTable}.model_type", static::class)
            ->whereNull("{$modelRoleTable}.{$teamKey}")
            ->whereNull("{$roleTable}.team_id")
            ->where("{$roleTable}.guard_name", 'web')
            ->where("{$roleTable}.name", 'developer')
            ->exists();
    }

    private function isDeveloperOnlyMenu(Menu $menu): bool
    {
        return in_array($menu->route, [
            'roles.index',
            'menus.index',
        ], true);
    }

    private function isDeveloperOnlyPermission(string $permission): bool
    {
        return str_starts_with($permission, 'role.')
            || str_starts_with($permission, 'permission.');
    }

    /**
     * Get the guard name used by Spatie permissions.
     */
    public function guardName(): string
    {
        return 'web';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
