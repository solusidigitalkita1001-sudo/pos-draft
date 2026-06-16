# AI Agent Rules

Before generating code:

Understand:

- Team Scope
- Permission
- Existing Action Pattern

Follow:

- Laravel 13 conventions
- React + Inertia conventions
- TypeScript best practices

Never:

- Create Repository Pattern
- Add unnecessary abstractions
- Put business logic in controllers

Always:

- Enforce team scope on business data.
- Use permissions instead of hardcoded roles.
- Respect system.global-access permission when cross-team access is required.