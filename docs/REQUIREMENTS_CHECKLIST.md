# Requirements checklist

Each section of the specification mapped to where it is implemented. ✅ implemented · ⚠️ implemented with a stated limitation.

## 2. Technology stack
| Requirement | Status | Where |
|---|---|---|
| Laravel (latest), PHP 8.3+, MySQL 8+ | ✅ | Laravel 13, PHP 8.3, tested on MariaDB 10.11 + SQLite |
| Eloquent, migrations, seeders, factories, policies, form requests | ✅ | `app/Models`, `database/*`, `app/Policies`, `app/Http/Requests` (Vehicle, Part, Customer, Supplier, Invoice, StorePayment; other modules validate in controller) |
| Queues & notifications | ✅ | database queue, `app/Notifications` |
| REST API | ✅ | `routes/api.php`, `docs/API.md` |
| Blade, Bootstrap 5, JS, AJAX/Fetch | ✅ | `resources/views`, `resources/js` |
| DataTables or equivalent | ✅ | `resources/js/core/datatable.js` (server-side) |
| Select2 or equivalent | ✅ | Tom Select (`core/selects.js`) |
| Chart.js, modals, toasts, responsive | ✅ | dashboard, reports, `x-modal`, `core/http.js` toasts |
| Spatie Permission | ✅ | `config/permission.php`, `autoshop:sync-permissions` |
| Auth starter kit | ⚠️ | Laravel's starter kits are Tailwind/React/Vue/Livewire; authentication is implemented directly on Laravel's auth, password broker and verification APIs with Bootstrap views (`Auth\*Controller`) |
| Barcode/QR, PDF, Excel/CSV, camera, NHTSA vPIC | ✅ | simple-qrcode + Code39 SVG, DomPDF, maatwebsite/excel + CSV, html5-qrcode + tesseract.js, `VinDecoderService` |

## 3. Registration, login & permissions
| Requirement | Status | Where |
|---|---|---|
| Registration, login/logout, forgot/reset password, email verification | ✅ | `AuthController`, `PasswordResetController`, `VerificationController` |
| Optional SMS verification, optional 2FA | ✅ | `VerificationController@phone*`, `TwoFactorController` (TOTP / email / SMS + recovery codes) |
| Remember me, change password, update profile | ✅ | login form, `ProfileController` |
| Login history, active session management | ✅ | `login_histories`, Profile → Security (end one/all other sessions) |
| Account suspension / reactivation | ✅ | Users → status actions (sessions & API tokens revoked on suspend) |
| Approval flow steps 1–7 and 5 statuses | ✅ | `register()` pending + `user_approval_requests`, `NewUserRegistered`, `EnsureAccountApproved`, `auth/pending`, `UserController@status`, `AccountStatusChanged` |
| 11 default roles + custom roles | ✅ | `config/autoshop.php`, `RoleController` permission matrix |
| Module permissions (view…manage settings) | ✅ | ~100 `module.action` permissions |
| Page, action and record-level enforcement on backend | ✅ | route `permission:` middleware, `can()` checks, policies & `ServiceJob::visibleTo`, own-attendance/tasks/payslips |

## 4. Dashboard
| Requirement | Status | Where |
|---|---|---|
| 16 cards | ✅ | `DashboardService::cards` |
| 7 charts | ✅ | `DashboardService::charts` |
| Activity timeline, pending approvals, alerts, quick actions | ✅ | `dashboard.blade.php` |
| AJAX refresh, permission-aware widgets | ✅ | `/dashboard/data`, every widget declares its permission |

## 5. Vehicles
| Requirement | Status | Where |
|---|---|---|
| List / add / edit-view pages, all 21 columns | ✅ | `vehicles/*` |
| All AJAX filters & sorts, custom columns, pagination 10/25/50/100, table + card view | ✅ | `VehicleController::table`, datatable component |
| All add-vehicle fields, multiple images, documents, ownership types | ✅ | `VehicleRequest`, `vehicles/form` |
| VIN camera scanner (permission, barcode/OCR, 17-char validation, decode, autofill, manual correction, manual fallback), HTTPS note, honest results | ✅ | `resources/js/vin-scanner.js`, `VinDecoderService`, `Support\Vin` check digit |
| Repair statuses, customisable, history with dates/notes/employee | ✅ | `vehicle_statuses`, Settings → Repair Statuses, `vehicle_status_histories` |
| Financial tracking (all 12 values), payment statuses, no double counting | ✅ | `VehicleCostService` |

## 6. Parts
| Requirement | Status | Where |
|---|---|---|
| Parts list / add / edit / details / movement history | ✅ | `parts/*` |
| All part fields | ✅ | `PartRequest` |
| Stock statuses available/low/out/reserved/damaged/returned | ✅ | `stock_status` + filters for reservations, damaged, returns |
| Add / remove / adjust / reserve / assign to vehicles / installed on repairs / supplier purchases / returns / transfers / history | ✅ | `InventoryService`, `PartController@stock`, POs, service-job part lines |
| Atomic transactions, negative stock prevented unless authorised | ✅ | row locks + `parts.negative_stock` permission |
| AJAX list, low-stock alerts, valuation report | ✅ | `LowStockAlert`, dashboard, `parts_valuation` report |

## 7. Employees, attendance, payroll
| Requirement | Status | Where |
|---|---|---|
| List / add / edit / profile / attendance / payroll / payment history | ✅ | `employees/*`, `attendance/index`, `payroll/*` |
| All employee fields, 6 pay types | ✅ | `employees` + `employee_pay_rates` |
| Clock in/out, breaks, hours, overtime, 5 statuses, self-service, audited corrections | ✅ | `AttendanceService` |
| Payroll fields, 4 payment statuses, summaries, payslips | ✅ | `PayrollService`, `payroll.payslip` PDF |
| Configurable rules, historical rates, not a "legally complete" calculation | ⚠️ | by design — statutory withholding entered as deductions (stated in UI and docs) |

## 8. Interior repair, wash & detailing
| Requirement | Status | Where |
|---|---|---|
| Service job list / add / edit / details | ✅ | `service-jobs/*` (category filter: repair, interior, cleaning) |
| Interior & cleaning service types | ✅ | seeded `service_types`, editable in Settings → Lookups |
| All job fields incl. before/after images | ✅ | `service_jobs`, `service_job_items`, `service_job_images` |
| 7 statuses (+ cancelled) | ✅ | `config/autoshop.php` |
| Multiple employees, multiple services, costs linked to vehicle & invoice | ✅ | `service_job_employees`, `VehicleCostService`, invoice service_job lines |

## 9. Sales, billing & invoicing
| Requirement | Status | Where |
|---|---|---|
| All 7 pages | ✅ | invoices index/create/edit/show, payments create/index, customer statement |
| 6 invoice types, all invoice information | ✅ | `InvoiceService` |
| 7 payment options; full/partial/installments/deposit/outstanding/refund/reversal/financing reference | ✅ | `PaymentService` |
| Financing recorded separately, not cash before settlement | ✅ | `financing_records`, `settleFinancing` |
| Numbering, printable, logo, PDF, email, receipts, history, reminders, search, status, export | ✅ | `InvoicePdf`, `InvoiceIssued`, `PaymentReminder`, scheduler |
| Transactions, audit trail, no raw card data | ✅ | DB transactions, `Auditable` + explicit audit events, PAN guard |

## 10. Customers & suppliers
| Requirement | Status | Where |
|---|---|---|
| Customer list/edit, contact, owned vehicles, purchases, repairs, invoices, payments, balance, notes, attachments | ✅ | `customers/show` |
| Supplier fields, parts, POs, history, paid, pending, notes | ✅ | `suppliers/show`, `purchase-orders/*` |
| Searchable selectors everywhere | ✅ | `/lookup/{type}` + `x-form.remote` |

## 11. Settings
| Requirement | Status | Where |
|---|---|---|
| General settings (all) | ⚠️ | all present; *Language* offers English only (UI strings are not translated yet) |
| Theme settings + live preview + persistence | ✅ | Settings → Theme |
| Email settings + test + toggles | ✅ | Settings → Email (password-reset email is always on — it is a mandatory security function) |
| SMS settings, providers, test, expiry, rate limits, email OTP alternative, no "free SMS" claim | ✅ | Settings → SMS, `Sms\*Provider` |
| Verification toggles; mandatory protections preserved | ✅ | Settings → Verification |
| API integrations with encrypted secrets, never exposed | ✅ | `api_integrations.credentials` (`encrypted:array`, hidden), encrypted settings |
| Users/roles/permissions, notification prefs, low stock, reminders, invoice templates, backups, retention, audit logs, maintenance, integration logs, session security | ✅ | Settings center + Profile |

## 12. Global search & list behaviour
| Requirement | Status | Where |
|---|---|---|
| Global search across 8 modules, authorised only | ✅ | `SearchController` |
| AJAX search, server pagination, sorting, multi-filter, date range, saved presets, reset, export CSV/Excel, print, bulk actions with permission checks, loading/error/empty states, URL sync | ✅ | datatable component on every list (bulk actions on vehicles, parts, users, customers, suppliers, employees, attendance, service jobs, tasks, invoices; financial records such as payments are individually reversed by design) |

## 13–14. UI/UX & reports
| Requirement | Status | Where |
|---|---|---|
| Sidebar items, top navigation, mobile layout, camera scanning, cards, touch-friendly | ✅ | `partials/sidebar`, `partials/topbar`, CSS breakpoints, collapsible filters |
| 16 reports (+ parts valuation), filters, PDF/Excel/CSV export, clear revenue/cash/expenses/receivables/profit distinction | ✅ | `ReportService`, `reports/*` |

## 15–17. Database, security, additional features
| Requirement | Status | Where |
|---|---|---|
| All suggested tables (+ supporting tables) with FKs, indexes, decimals, soft deletes | ✅ | `database/migrations`, `docs/ARCHITECTURE.md` ER diagram |
| CSRF, XSS, SQLi, hashing, rate limiting, reset tokens, OTP expiry, RBAC, object-level auth, upload validation, private attachments, credential encryption, audit, session expiry, production config, backup/restore | ✅ | middleware, policies, `Uploads`, private disk, `docs/DEPLOYMENT.md` |
| Activity log, notification center, job assignment & checklist, inspection checklist, photos, warranty, documents, reminders, tasks, labels/QR, bulk imports, info sheet, quick add, stock alerts, scheduled backups, import/export, custom vehicle fields | ✅ | see README feature table |

## 18. Deliverables
Folder structure, migrations, models, controllers, form requests, middleware & policies, routes, Blade, JS/AJAX, seeders, default roles/permissions, example settings (`config/autoshop.php` defaults), invoice PDF templates (classic/modern/compact), VIN scanner, email templates (`resources/views/vendor/mail`), SMS provider interface, API configuration, automated tests (67), `.env.example`, installation & deployment instructions, README — ✅

## Known limitations (stated honestly)
* Camera scanning requires HTTPS/localhost and a camera; OCR quality depends on lighting and the printed font; manual entry is always available.
* NHTSA vPIC coverage is best for vehicles sold in the US; other markets may decode partially (the app never fills fields with guessed data).
* SMS, email delivery and payment gateways require your own provider accounts.
* Payroll does not compute statutory taxes; UI is English-only.
* Notification preferences support in-app and email channels; SMS is used for verification/2FA codes only.
