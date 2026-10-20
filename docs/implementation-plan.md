# Implementation plan

## Repository assessment

- New isolated repository: `C:\\Users\\wondr\\HermesWorkspace\\big-market-pos`, branch `main`; the workspace was not itself a Git repository.
- The product brief specifies Laravel/Blade, PostgreSQL, branch isolation, Indonesian UI, IDR, `Asia/Jakarta`, and transactional sales/inventory.
- Environment: PHP 8.3.33, Node 26.10.0, npm 11.19.1. Composer was absent globally; Composer 2.10.3 was downloaded to user-local Temp and SHA-256 verified against the official checksum. Laravel framework 13.38 and skeleton 13.11.0 are installed from lockfiles.
- PHP extension DLLs for `pdo_pgsql`, `fileinfo`, and `zip` are present but disabled in the default `php.ini`; commands load them with process-local `-d extension=...` flags. The PostgreSQL PDO driver loads (libpq 14.23) and BCMath is required by inventory services.
- Default feature tests use SQLite; a temporary local PostgreSQL 17 Docker container was started for real migration and checkout verification, then removed after the checks.
- Search tests force SQLite `LIKE` to be case-sensitive to reproduce the behavior expected on PostgreSQL and protect case-insensitive product, POS, and transaction-number searches.
- SQLite-only `PRAGMA case_sensitive_like` setup in those search tests is now conditional, allowing the same tests to run against PostgreSQL without executing SQLite-specific SQL.
- Added PostgreSQL-only tests that prove a second PDO connection blocks on `FOR UPDATE` and that two separate PHP processes attempting to sell against the same stock balance cannot oversell.

## Implemented and remaining

- [x] Initialize a new repository without modifying other workspace projects.
- [x] Scaffold Laravel 13 + Blade + Tailwind/Vite; install Composer and npm dependencies.
- [x] Configure PostgreSQL, Indonesian locale, and `Asia/Jakarta` defaults in `.env.example`.
- [x] Add branches, users' active status, roles, permissions, user-role and user-branch assignments.
- [x] Add idempotent role/permission seeder without default/demo user credentials.
- [x] Implement rate-limited login/logout, session renewal/invalidation, inactive-account lockout, first-admin/branch bootstrap command, branch selector, and server-side branch/permission middleware.
- [x] Add branch-scoped catalog product search, listing and create flow. Product creation atomically creates the global item, selected-branch price/minimum-stock mapping, and zero balance; SKU/barcode uniqueness and decimal precision are validated.
- [x] Add category/product/branch-price/stock-balance/inventory-movement schema and a permission-checked inventory adjustment service with BCMath, row locking, no-negative-stock protection, transaction rollback, actor and source traceability.
- [x] Add Indonesian login, branch selection, dashboard, catalog and product form screens.
- [x] Add a transactional cash POS workflow: branch-scoped product search, server-derived price/tax, stock row locks, nonnegative stock enforcement, idempotent checkout, sale/line snapshots, cash received/change, and printable receipt.
- [x] Add permission- and active-branch-scoped sales history with transaction-number search and receipt links.
- [x] Add branch- and permission-scoped sales reporting with selectable date ranges (maximum 31 days), daily totals, transaction count, tax, cost, and decimal-safe gross-profit calculation.
- [ ] Add product editing/import, category administration, inventory movement UI, stock counts, transfers, and goods receiving.
- [x] Add branch-scoped expense entry, seeded baseline categories, approval/rejection with reasons, actor/timestamp attribution, self-approval protection, and row-locked decisions.
- [ ] Add cashier shifts, refunds, purchasing, supplier payments, broader financial reporting that includes approved expenses, expense-category administration, and evidence uploads.
- [ ] Add user/branch administration UI and append-only audit-log workflows.
- [x] Run the complete feature suite against a disposable PostgreSQL 17 database, apply all migrations, and exercise row locking/concurrent checkout.
- [ ] Enable PostgreSQL extensions in normal PHP configuration and run against a persistent development server.
- [ ] Add verified gateway adapter only after provider and credentials are selected.

## Architecture decisions

- Modular Laravel monolith; Laravel 13, Blade, Tailwind 4/Vite, Eloquent, and first-party session authentication.
- PostgreSQL is the target database. The default suite uses SQLite; PostgreSQL migration and row-lock/concurrency behavior also have dedicated executed tests against a disposable PostgreSQL 17 container.
- Branch-scoped requests resolve the active branch from server session, then check branch assignment or explicit `branches.access_all`; apply `branch.access` to every future branch-scoped route.
- Catalog data is global; selling price, minimum stock, availability, and balance are branch scoped.
- Database money fields use DECIMAL/NUMERIC; stock quantities use 3 decimal places. Inventory adjustment arithmetic uses BCMath and writes balance plus immutable-style movement record within one transaction.
- Payment provider is not selected; no live or fake gateway is claimed.

## Verification evidence

- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan test` — passed: 55 tests total, 53 passed and 2 PostgreSQL-only tests skipped; 212 assertions on SQLite.
- Direct PHPUnit with `DB_CONNECTION=pgsql` and process-local `pdo_pgsql`, `fileinfo`, and `zip` extensions — passed: 55 tests, 222 assertions on PostgreSQL 17. `RefreshDatabase` successfully applied 8 migrations; post-run checks confirmed no leftover users, branches, sales, expenses, or expense categories. Direct PHPUnit was required because `artisan test` launches a child PHP process that does not inherit command-line `-d extension=...` flags.
- PostgreSQL concurrency coverage — passed: 2 tests, including real two-connection row-lock waiting and two concurrent application checkouts; one checkout succeeded, one was rejected for insufficient stock, and the balance remained nonnegative.
- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan test --filter=SaleCheckoutTest` — passed: 16 tests, 55 assertions, including case-sensitive-LIKE and decimal-range regressions.
- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan test --filter=InventoryAdjustmentTest` — passed: 6 tests, 19 assertions, including DECIMAL upper-bound validation.
- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan test --filter=ProductCatalogTest` — passed: 6 tests, 23 assertions, including case-insensitive search under case-sensitive LIKE.
- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan test --filter=SalesReportTest` — passed: 5 tests, 15 assertions, covering branch scope, totals, negative gross profit, date-range limit, and access control.
- `npm run build` — passed with Vite 8.3.4; non-blocking Laravel font plugin notice that optional `fontaine` is not installed.
- `vendor/bin/pint --dirty --test --format agent` — passed.
- `php 'C:/Users/wondr/AppData/Local/Temp/big-market-bootstrap/composer.phar' validate --no-check-publish` — passed. Composer is not installed globally/on PATH in this environment.
- `php 'C:/Users/wondr/AppData/Local/Temp/big-market-bootstrap/composer.phar' audit --locked` — no security advisories found.
- `npm audit --audit-level=moderate` — 0 vulnerabilities.
- `php -d extension=pdo_pgsql -d extension=fileinfo -d extension=zip artisan route:list --except-vendor --path=expenses` — passed; all 5 expense routes are registered with their permission middleware.
- Offline Laravel PostgreSQL query-grammar smoke check — passed; `lockForUpdate()` compiles to `select * from "inventory_balances" where "id" = ? for update`. This verifies SQL generation only, not execution, migrations, or actual lock behavior.
- Extracted POS inline JavaScript and ran `node --check` — passed.
- PostgreSQL migrations and test-level concurrent checkout were exercised against a disposable local PostgreSQL 17 container; the container was stopped and removed after verification. No authenticated real-browser journey, production deployment, broader concurrent workload/stress test, or live-payment path was verified. Use only an empty, disposable database when running the suite with `DB_CONNECTION=pgsql`; never point `RefreshDatabase` tests at a database containing user data. SQLite runs skip the two PostgreSQL-specific concurrency tests.

## Current milestone

The application now has its security/access foundation, branch-scoped catalog, inventory adjustment backend, transactional cash checkout/receipt, permission-scoped sales history, a date-filtered sales report, and a basic branch-scoped expense approval workflow. It is still an incomplete supermarket system: purchasing/receiving, refunds, shifts, broader financial reporting, administration screens, and persistent-environment/production verification remain open work. Expense methods are recorded only as metadata and are not yet reconciled against shifts or included in the financial report.


