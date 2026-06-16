# POS Internal

POS Internal adalah aplikasi Point of Sales (POS) berbasis web yang dibangun menggunakan Laravel dan React untuk mendukung operasional penjualan, manajemen produk, inventori, voucher, promosi, transaksi, refund, dan return dalam lingkungan multi-team.

## Features

### Team Management

* Multi Team Support
* Team Switching
* Team Invitations
* Team Member Management

### User & Access Management

* Authentication (Laravel Fortify)
* Role Management
* Permission Management
* Menu Management
* User Management

### Product Management

* Product Management
* Product Categories
* Product Packages
* Product Promotions
* Product Stock Management
* Product Activity History

### Point of Sales

* Product Search
* Cart Management
* Voucher Validation
* Payment Processing
* Transaction Management

### Transaction Management

* Sales Transactions
* Transaction Refunds
* Transaction Returns
* Transaction History
* Transaction Export

### Security

* Team Data Isolation
* Permission-Based Access Control
* Two Factor Authentication
* Password Confirmation Protection

---

## Technology Stack

### Backend

* Laravel 13
* PHP 8.3+
* Laravel Fortify
* Spatie Laravel Permission

### Frontend

* React 19
* TypeScript
* Inertia.js
* Vite

### Database

* MySQL

### Testing

* Pest PHP

### Deployment

* Docker

---

## Architecture

The application follows Action Pattern architecture.

```text
React Page
    ↓
Controller
    ↓
Action
    ↓
Model
    ↓
Database
```

### Principles

* Thin Controllers
* Action-Oriented Business Logic
* Team Scoped Data
* Permission Driven Authorization
* Transaction Safe Inventory Updates

---

## Project Structure

```text
app/
├── Actions
├── Http
├── Models
├── Policies
├── Providers

resources/
├── js
│   ├── components
│   ├── layouts
│   ├── pages
│   ├── hooks
│   └── types

database/
├── migrations
├── seeders

docs/
.ai/
modules/
prompts/
```

---

## Installation

### Clone Repository

```bash
git clone https://github.com/irvansindy/pos-internal.git

cd pos-internal
```

### Install Dependencies

```bash
composer install

npm install
```

### Environment Setup

```bash
cp .env.example .env

php artisan key:generate
```

### Database Migration

```bash
php artisan migrate
```

### Run Development Server

```bash
composer run dev
```

---

## Testing

Run all tests:

```bash
php artisan test
```

Or:

```bash
composer test
```

---

## Documentation

Additional documentation can be found in:

```text
.ai/
docs/
modules/
prompts/
```

### Documentation Structure

```text
.ai/
    AI Agent Context & Rules

docs/
    Business & Technical Documentation

modules/
    Module Documentation

prompts/
    AI Development Prompts
```

---

## Development Rules

### Required

* Team Scope Validation
* Permission Validation
* Request Validation
* Action Pattern
* Automated Testing

### Avoid

* Business Logic in Controllers
* Hardcoded Team Access
* Unscoped Queries
* Massive Actions

---

## License

This project is developed for internal business operations.

All rights reserved.
