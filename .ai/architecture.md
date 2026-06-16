# Application Architecture

This project follows Action Pattern architecture.

Flow:

React Page
↓
Controller
↓
Action
↓
Model
↓
Database

## Rules

Controllers must stay thin.

Controllers are responsible for:

- Request handling
- Authorization
- Calling Action
- Returning Response

Business logic must be placed inside Actions.

Models are responsible only for:

- Relationships
- Scopes
- Accessors
- Mutators

Avoid business logic in Models.