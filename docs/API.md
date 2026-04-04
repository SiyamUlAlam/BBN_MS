# API Specification

## Conventions

- Base URL: `/api`
- Request/response format: JSON
- Success shape:

```json
{
  "status": "success",
  "data": {}
}
```

- Error shape:

```json
{
  "status": "error",
  "message": "Readable message"
}
```

## Endpoints

### 1. Health

- `GET /api/health`

Response example:

```json
{
  "status": "ok",
  "service": "BBN ISP Management API",
  "timestamp": "2026-04-03T10:00:00+00:00"
}
```

### 2. Customers

- `GET /api/customers?limit=50`
- `POST /api/customers`

Request example:

```json
{
  "customer_id": "CUST-1001",
  "full_name": "John Doe",
  "phone": "017XXXXXXXX",
  "email": "john@example.com",
  "nid": "1234567890",
  "address": "Road 10",
  "zone_id": "zone-dhaka-north",
  "area_id": "area-mirpur-1",
  "package_id": "pkg-20mbps",
  "monthly_bill_amount": 1200,
  "due_amount": 0,
  "line_source_id": "line-src-1",
  "distribution_box_id": "box-12"
}
```

### 3. Products

- `GET /api/products?limit=100`
- `POST /api/products`

Request example:

```json
{
  "sku": "ONU-001",
  "name": "XPON ONU",
  "category": "onu",
  "price": 2500,
  "stock": 20,
  "reorder_level": 5
}
```

### 4. Connection Cost Preview

- `POST /api/connections/preview-cost`

Request example:

```json
{
  "items": [
    { "product_id": "660123456789012345678901", "quantity": 1 },
    { "product_id": "660123456789012345678902", "quantity": 2 }
  ],
  "service_charge": 500
}
```

Response includes line totals, products total, service charge, and grand total.

### 5. Topology

- `GET /api/zones`
- `POST /api/zones`
- `GET /api/areas?zone_id=<zoneId>`
- `POST /api/areas`
- `GET /api/line-sources`
- `POST /api/line-sources`
- `GET /api/distribution-boxes?zone_id=<zoneId>`
- `POST /api/distribution-boxes`

### 6. Packages

- `GET /api/packages`
- `POST /api/packages`

Create package request example:

```json
{
  "name": "30 Mbps Home",
  "speed_mbps": 30,
  "monthly_price": 1500,
  "installation_charge": 2000,
  "status": "active"
}
```

### 7. Connections

- `GET /api/connections`
- `POST /api/connections`
- `GET /print/connection-summary?id=<connectionOrderId>`

Create connection request example:

```json
{
  "customer_id": "CUST-1001",
  "technician": "Tech Team A",
  "line_source_id": "LS-1",
  "distribution_box_id": "660123456789012345678901",
  "service_charge": 500,
  "items": [
    { "product_id": "660123456789012345678902", "quantity": 1 },
    { "product_id": "660123456789012345678903", "quantity": 2 }
  ]
}
```

### 8. Billing and Payments

- `POST /api/billing/generate-monthly`
- `GET /api/bills?month=YYYY-MM`
- `POST /api/payments`
- `GET /api/payments?customer_id=CUST-1001`

Generate monthly bills request example:

```json
{
  "month": "2026-04"
}
```

Payment request example:

```json
{
  "customer_id": "CUST-1001",
  "bill_month": "2026-04",
  "amount": 1000,
  "method": "cash",
  "collector": "Accounts User",
  "reference": "RCPT-0001"
}
```

### 9. Finance

- `POST /api/finance/income`
- `GET /api/finance/income?month=YYYY-MM`
- `POST /api/finance/expenses`
- `GET /api/finance/expenses?month=YYYY-MM`
- `GET /api/finance/summary?month=YYYY-MM`

### 10. Dashboard and Reports

- `GET /api/dashboard/summary?month=YYYY-MM`
- `GET /api/reports/income-expense/print?month=YYYY-MM`

## Notes

- Root route `GET /` serves a lightweight web starter dashboard page for navigation.
- Print endpoints return HTML suitable for browser printing or PDF rendering.
