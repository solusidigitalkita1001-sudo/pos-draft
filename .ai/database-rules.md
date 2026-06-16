# Database Rules

## Team Scope

Every business table must belong to a team.

Examples:

- products
- transactions
- vouchers

## Transactions

Any stock modification must be wrapped in:

DB::transaction()

Examples:

- Sales
- Refunds
- Returns
- Stock Adjustment

## Foreign Keys

Always use foreign keys when possible.

## Soft Delete

Use Soft Delete for:

- Products
- Categories
- Vouchers