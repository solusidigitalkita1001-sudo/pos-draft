# Folder Structure

## Overview

POS Internal menggunakan struktur Laravel modern dengan React + TypeScript + Inertia.js.

Project mengikuti Action Pattern Architecture untuk menjaga business logic tetap terorganisir dan mudah dipelihara.

---

# Root Structure

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
tests/

.ai/
docs/
modules/
prompts/
```

---

# Application Layer

## app/

Folder utama aplikasi Laravel.

```text
app/
├── Actions
├── Http
├── Models
├── Policies
├── Providers
```

---

# Actions

## app/Actions

Berisi business logic aplikasi.

```text
app/Actions/

├── Dashboard/
├── Menu/
├── Permission/
├── Pos/
├── Product/
├── ProductCategory/
├── ProductPackage/
├── ProductPromotion/
├── ProductStock/
├── Role/
├── Team/
├── Transaction/
├── User/
└── Voucher/
```

### Rules

* Satu Action memiliki satu tanggung jawab.
* Business logic harus ditempatkan di Action.
* Controller hanya memanggil Action.
* Hindari Action dengan ukuran terlalu besar.

### Example

```php
CreateProductAction
UpdateProductAction
DeleteProductAction

CreateTransactionAction
RefundTransactionAction
ReturnTransactionAction
```

---

# Controllers

## app/Http/Controllers

Berisi controller untuk menerima request dan memanggil Action.

### Responsibilities

* Authorization
* Request Validation
* Call Action
* Return Response

### Not Allowed

* Query database langsung
* Business logic kompleks
* Perhitungan transaksi

---

# Requests

## app/Http/Requests

Berisi validasi request.

### Example

```php
StoreProductRequest
UpdateProductRequest

StoreVoucherRequest
UpdateVoucherRequest
```

### Rules

Semua input user wajib divalidasi menggunakan Form Request.

---

# Middleware

## app/Http/Middleware

Berisi middleware aplikasi.

### Examples

* Team Scope Middleware
* Permission Middleware
* Current Team Middleware

---

# Models

## app/Models

Berisi representasi tabel database.

### Responsibilities

* Relationships
* Scopes
* Accessors
* Mutators

### Not Recommended

* Business Logic Kompleks
* Transaction Processing

---

# Policies

## app/Policies

Berisi authorization rules.

### Example

```php
ProductPolicy
TransactionPolicy
VoucherPolicy
```

### Rules

Gunakan Policy untuk seluruh authorization yang berkaitan dengan model.

---

# Database Layer

## database/

```text
database/
├── factories
├── migrations
├── seeders
```

---

## database/migrations

Berisi perubahan schema database.

### Rules

Setiap perubahan database wajib melalui migration.

Jangan mengubah schema langsung melalui database.

---

## database/seeders

Berisi data awal sistem.

### Examples

```php
RoleSeeder
PermissionSeeder
MenuSeeder
```

---

# Frontend Layer

## resources/js

Frontend React + TypeScript.

```text
resources/js/

├── actions
├── components
├── hooks
├── layouts
├── lib
├── pages
├── routes
├── types
└── wayfinder
```

---

# Pages

## resources/js/pages

Berisi halaman aplikasi.

### Examples

```text
Dashboard/

Products/
Categories/
Transactions/
Vouchers/

Users/
Roles/
Permissions/
```

### Rules

Pages hanya berisi:

* UI
* Form
* State Management

Business logic tetap di backend.

---

# Components

## resources/js/components

Reusable UI Components.

### Examples

```text
Table
Form
Modal
Select
Input
Button
```

### Rules

Komponen harus reusable.

Hindari business logic pada component.

---

# Layouts

## resources/js/layouts

Layout utama aplikasi.

### Examples

```text
AppLayout
AuthLayout
GuestLayout
```

---

# Hooks

## resources/js/hooks

Custom React Hooks.

### Examples

```typescript
usePermission()
useCurrentTeam()
useTransaction()
```

---

# Types

## resources/js/types

TypeScript Interfaces dan Types.

### Examples

```typescript
Product
Transaction
Voucher
User
Team
```

---

# Routing

## routes/

```text
routes/
├── web.php
├── auth.php
└── settings.php
```

### Rules

Semua route harus:

* Menggunakan middleware yang sesuai
* Mendukung Team Scope
* Mendukung Permission

---

# Testing

## tests/

```text
tests/
├── Feature
└── Unit
```

### Feature Test

Digunakan untuk:

* HTTP Request
* Authorization
* Team Scope
* Permission

### Unit Test

Digunakan untuk:

* Action
* Helper
* Utility

---

# AI Documentation

## .ai/

Dokumentasi khusus AI Agent.

### Purpose

Membantu AI memahami:

* Arsitektur aplikasi
* Workflow development
* Coding standards
* Team scope rules

---

# Technical Documentation

## docs/

Dokumentasi teknis dan bisnis project.

---

# Module Documentation

## modules/

Dokumentasi tiap modul bisnis.

### Examples

```text
products.md
transactions.md
vouchers.md
users.md
```

---

# AI Prompts

## prompts/

Berisi prompt dan aturan tambahan untuk AI Agent.

### Purpose

Membantu AI menghasilkan kode yang konsisten dengan arsitektur project.

---

# Architecture Summary

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

## Principles

* Thin Controllers
* Action Pattern
* Team Scoped Data
* Permission Based Access
* Transaction Safe Operations
* Reusable Components
* Type Safe Frontend

```
```
