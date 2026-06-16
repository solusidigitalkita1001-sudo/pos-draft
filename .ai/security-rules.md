# Security Rules

## Authorization

Every action must check permissions.

Never trust frontend permissions.

## Validation

Validate all user input.

## Team Scope

User must never access another team's data.

All queries must be scoped.

Bad:

Product::find($id)

Good:

Product::where('team_id', $currentTeamId)
       ->findOrFail($id)