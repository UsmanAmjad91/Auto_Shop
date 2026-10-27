# REST API v1

Base URL: `https://your-domain/api/v1` · JSON only (`Accept: application/json`) · Rate limit 120 requests/minute per user.

## Authentication
Laravel Sanctum bearer tokens. Tokens carry the user's role permissions; every endpoint enforces the same permission as the web UI. Pending/suspended accounts are refused.

```http
POST /api/v1/auth/token
{ "email": "user@example.com", "password": "…", "device_name": "Shop tablet" }
→ 200 { "token": "1|abc…", "expires_in_days": 90 }
```
Accounts with two-factor authentication must create tokens in the web UI (**Profile → Security → API tokens**).

```http
Authorization: Bearer 1|abc…
GET    /api/v1/me                 → user, roles, permissions
DELETE /api/v1/auth/token         → revoke current token
```

## Endpoints

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/dashboard` | dashboard.view | KPI values and alerts the user may see |
| POST | `/vin/decode` | vehicles.create or vehicles.edit | `{ "vin": "…", "year": 2019 }` → validation, decoded fields, duplicate check |
| GET | `/vehicles` | vehicles.view | `q, inventory_status, repair_status, make, year, per_page (≤100)` |
| POST | `/vehicles` | vehicles.create | Same fields as the web form (VIN, make, model, year, body_type, ownership_type …) |
| GET | `/vehicles/{id}` | vehicles.view | `costs` block only with vehicles.view_costs |
| PUT | `/vehicles/{id}` | vehicles.edit | |
| DELETE | `/vehicles/{id}` | vehicles.delete | Refused when the vehicle has invoices |
| GET | `/parts` | parts.view | `q` matches name, part #, OEM, SKU, barcode; `stock_status` |
| GET | `/parts/{id}` | parts.view | Includes stock per location and compatibility |
| POST | `/parts/{id}/stock` | parts.adjust_stock | `{ "operation": "add\|remove\|adjust\|reserve\|unreserve\|transfer\|damaged\|return_to_supplier", "quantity": 2, "location_id": 1, "to_location_id": 2, "notes": "…" }` — atomic, negative stock refused |
| GET | `/customers` | customers.view | `q` |
| POST | `/customers` | customers.create | |
| GET | `/customers/{id}` | customers.view | Includes vehicles; balance only with invoices.view |
| GET | `/service-jobs` | service_jobs.view | Only assigned jobs unless service_jobs.view_all; `category, status` |
| GET | `/service-jobs/{id}` | service_jobs.view + record access | |
| POST | `/service-jobs/{id}/status` | service_jobs.edit + record access | `scheduled, in_progress, on_hold, completed` (approval statuses require the web UI) |
| GET | `/invoices` | invoices.view | `status, customer_id` |
| GET | `/invoices/{id}` | invoices.view | Items and financing |
| POST | `/invoices/{id}/payments` | payments.create | `{ "amount": 100, "payment_date": "2026-10-26", "method": "cash\|credit_card\|debit_card\|bank_transfer\|check\|other", "reference": "last4 4242", "type": "payment\|deposit" }` |

## Errors
* `401` missing/invalid token · `403` missing permission or account not approved · `404` not found
* `422` validation or business-rule error: `{ "message": "…", "errors": { "field": ["…"] } }`
* `429` rate limited

## Example
```bash
TOKEN=$(curl -s -X POST https://autoshop.example.com/api/v1/auth/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"…","device_name":"cli"}' | jq -r .token)

curl -s https://autoshop.example.com/api/v1/vehicles?inventory_status=available \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```
