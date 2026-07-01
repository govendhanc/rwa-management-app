# REST API Documentation

Base URL: `/api`

All endpoints except `/auth/login` require:

```http
Authorization: Bearer <jwt>
```

## Authentication

### POST `/auth/login`

Request:

```json
{
  "email": "admin@rwa.local",
  "password": "Admin@123"
}
```

Response:

```json
{
  "token": "jwt-token",
  "user": {
    "id": "uuid",
    "name": "System Admin",
    "email": "admin@rwa.local",
    "role": "Admin"
  }
}
```

### GET `/auth/me`

Returns the logged-in user.

## Dashboard

### GET `/dashboard`

Returns top card totals and chart datasets:

- total plots
- total owners
- paid this month
- pending this month
- total collection
- total expenses
- available balance
- total outstanding
- monthly collection trend
- expense by category
- paid vs pending
- income vs expense

## Owners

### GET `/owners?search=A-001`

Search by plot number, owner name, or mobile number.

### POST `/owners`

Role: `Admin`

Creates a plot and linked owner.

## Maintenance

### GET `/maintenance?month=6&year=2026&status=Paid`

Returns maintenance rows with plot and owner details.

### POST `/maintenance`

Role: `Admin`, `Treasurer`

Creates or updates monthly maintenance payment. The API calculates total, balance, and status.

### POST `/maintenance/generate-month`

Role: `Admin`, `Treasurer`

Request:

```json
{
  "month": 6,
  "year": 2026
}
```

Creates unpaid maintenance records for all plots and carries forward the last known balance.

## Finance

### GET `/finance/expenses`

Returns expenses.

### POST `/finance/expenses`

Role: `Admin`, `Treasurer`

### GET `/finance/income`

Returns income rows.

### POST `/finance/income`

Role: `Admin`, `Treasurer`

## Reports

### GET `/reports/:type`

Supported report types:

- `pending-list`
- `paid-list`
- `expense-report`
- `income-report`
- `cash-book`
- `outstanding-aging`

### GET `/reports/:type/export/excel`

Downloads `.xlsx`.

### GET `/reports/:type/export/pdf`

Downloads `.pdf`.

### GET `/reports/:type/export/csv`

Downloads `.csv`.

## Receipts

### GET `/receipts/:receiptNumber/pdf`

Downloads or previews a printable PDF receipt with QR payload.

## Notifications

### POST `/notifications/reminders`

Role: `Admin`, `Treasurer`

Request:

```json
{
  "month": 6,
  "year": 2026,
  "channel": "WhatsApp"
}
```

Returns queued reminder messages. Connect WhatsApp Business, SMS, or email provider credentials before enabling live sending.

## Users

### GET `/admin/users`

Role: `Admin`

### POST `/admin/users`

Role: `Admin`

Creates a user and assigns a role.

### PATCH `/admin/users/:id/status`

Role: `Admin`

Enables or disables a user.

### GET `/admin/audit-logs`

Role: `Admin`

Returns recent audit log entries.

## Settings

### GET `/settings`

Returns association details and configurable settings.

### PUT `/settings/association`

Role: `Admin`

Updates association master details.
