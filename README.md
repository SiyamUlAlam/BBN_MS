# BBN ISP Management System (PHP + MongoDB)

Professional web-based ISP management platform for customer operations, topology management, connection/inventory control, billing, finance, reporting, and dashboard analytics.

## Tech Stack

- PHP 8.2+
- MongoDB
- Composer
- MongoDB PHP Driver (`ext-mongodb`)
- `mongodb/mongodb` library

## Quick Start

1. Ensure required PHP extensions are enabled:

```bash
php -m | findstr /I mongodb
php -m | findstr /I zip
```

2. Install dependencies:

```bash
composer install
```

3. Copy environment file:

```bash
copy .env.example .env
```

4. Update MongoDB credentials in `.env`.

5. Serve app:

```bash
composer serve
```

6. Open startup pages:

- `http://localhost:8080/`
- `http://localhost:8080/api/health`

7. Login with default admin (first run):

- Username: `admin`
- Password: `admin123`

You can customize these from `.env` using:

- `DEFAULT_ADMIN_NAME`
- `DEFAULT_ADMIN_USERNAME`
- `DEFAULT_ADMIN_PASSWORD`

## Implemented Modules and API

- `GET /api/health`

### Customer Management

- `GET /api/customers`
- `POST /api/customers`
- `POST /api/customers/with-connection`

Customer creation now supports optional `connection_items` (array of `{product_id, quantity}`) so required connection equipment can be captured on the same page while creating a customer.
Use `POST /api/customers/with-connection` when you want one-step onboarding: customer create + connection order create + stock deduction + finance income entry.

Customer and connection setup are handled as separate modules.

### Topology (Zones, Areas, Line Source, Distribution Box)

- `GET /api/zones`
- `POST /api/zones`
- `GET /api/areas?zone_id=...`
- `POST /api/areas`
- `GET /api/line-sources`
- `POST /api/line-sources`
- `GET /api/distribution-boxes`
- `POST /api/distribution-boxes`

### Package Management

- `GET /api/packages`
- `POST /api/packages`

### Product Management

- `GET /api/products`
- `POST /api/products`

### Connection Management

- `POST /api/connections/preview-cost`
- `GET /api/connections`
- `POST /api/connections`
- `GET /print/connection-summary?id=...`

Connections support assigning many products/items for a single new line. You can add multiple items with quantity before creating the connection.

### Billing and Payments

- `POST /api/billing/generate-monthly`
- `GET /api/bills?month=YYYY-MM`
- `POST /api/payments`
- `GET /api/payments?customer_id=...`

### Income and Expense

- `POST /api/finance/income`
- `GET /api/finance/income?month=YYYY-MM`
- `POST /api/finance/expenses`
- `GET /api/finance/expenses?month=YYYY-MM`
- `GET /api/finance/summary?month=YYYY-MM`

### Dashboard and Print Reports

- `GET /api/dashboard/summary?month=YYYY-MM`
- `GET /reports`
- `GET /api/reports/overview?month=YYYY-MM`
- `GET /api/reports/income-expense/print?month=YYYY-MM`
- `GET /api/reports/income-expense/csv?month=YYYY-MM`
- `GET /api/reports/transactions/print?month=YYYY-MM`
- `GET /api/reports/transactions/csv?month=YYYY-MM`
- `GET /api/reports/bills/csv?month=YYYY-MM`
- `GET /api/reports/payments/csv?month=YYYY-MM`
- `GET /api/reports/customers/print`
- `GET /api/reports/customers/csv`
- `GET /api/reports/inventory/csv`
- `GET /api/reports/connections/csv`

### Users and Support Tickets

- `GET /api/users`
- `POST /api/users`
- `GET /api/tickets`
- `POST /api/tickets`
- `POST /api/tickets/status`

## Core Folder Structure

```text
public/
  index.php
src/
  Bootstrap.php
  Config/
  Controllers/
  Http/
  Repositories/
  Services/
database/
  indexes.js
docs/
  ARCHITECTURE.md
  API.md
```

## MongoDB Index Setup

Run in `mongosh`:

```javascript
load('database/indexes.js')
```

## Clear Input Data Before Delivery

Use the cleanup script to remove entered data from MongoDB.

Transactional data only (customers, connections, bills, payments, income, expense, tickets):

```bash
composer db:clear -- --yes
```

Clear everything entered in the app (also topology, packages, products, users):

```bash
composer db:clear -- --yes --all
```

After `--all`, the default admin account is recreated automatically when the app starts.

## Next Development Milestones

1. Auth + role-based access control
2. Ticketing and service complaint workflow
3. Frontend admin panel screens with forms and charts
4. Notifications (SMS/Email/WhatsApp) integrations
5. Audit logging and policy controls
6. Automated monthly billing job scheduler

See `docs/ARCHITECTURE.md` for module design and `docs/API.md` for API patterns.
