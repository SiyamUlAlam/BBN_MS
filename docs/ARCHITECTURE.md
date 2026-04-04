# Architecture Plan

## 1. Domain Modules

- Auth & RBAC
- Customers
- Network Topology (zones, areas, line sources, distribution boxes)
- Packages
- Connections & Installation
- Products & Inventory
- Billing & Payments
- Income & Expenses
- Reports & Printing
- Support Tickets
- Audit Logs

## 2. Service Boundaries

- Customer Service: registration, profile lifecycle, due overview
- Connection Service: installation request, assignment, activation
- Billing Service: monthly invoice generation, due carry-forward
- Payment Service: collection, receipt, reconciliation
- Finance Service: income-expense classification and reporting
- Reporting Service: printable summaries and statements

## 3. MongoDB Collection Plan

- users
- roles
- customers
- zones
- areas
- line_sources
- distribution_boxes
- packages
- products
- inventory_transactions
- connection_orders
- connection_order_items
- bills
- payments
- income_entries
- expense_entries
- audit_logs

## 4. Data Modeling Rules

- Keep customer profile in one document for frequent reads
- Keep high-growth transaction data in separate collections
- Avoid unbounded arrays in documents
- Store reporting snapshots in bill/payment documents to preserve historical accuracy
- Add schema validation (`$jsonSchema`) for critical collections

## 5. Event Flow (High Level)

1. Customer created -> status `pending_connection`
2. Connection order created -> products assigned -> installation total calculated
3. Connection completed -> customer status `active`
4. Monthly billing job creates invoices
5. Payments posted -> due updated
6. Income entries generated from collections
7. Expenses posted by accounts team
8. Dashboard aggregates monthly KPIs

## 6. Scalability and Reliability

- Use background workers for bill generation and bulk notifications
- Archive old records to cold collections for long-term retention
- Add caching for dashboard-heavy aggregations
- Use append-only financial ledgers to preserve accounting integrity
- Enable structured audit trail on sensitive operations
