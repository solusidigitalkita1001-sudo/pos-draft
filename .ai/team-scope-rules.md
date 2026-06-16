# Team Scope Rules

The application is multi-team.

All business data must be isolated by team.

Examples:

- Products
- Transactions
- Vouchers
- Promotions

## Default Rule

All queries must be scoped to current team.

Example:

Product::query()
    ->where('team_id', $currentTeam->id)

## Exception

Users with global-access permission may access cross-team data.

Example Permission:

system.global-access

Examples:

- Developer
- Super Admin

Roles must not be hardcoded.
Permissions should be used instead.