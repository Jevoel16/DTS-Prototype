# Changelog

All notable changes to this project will be documented in this file.

## Unreleased (audit fixes, see `documentation/AUDIT_REPORT_2026-10.md`)

**Upgrade:** run `composer run migrate`, then `composer run seal`. Everyone signs in again once (sessions moved to `storage/sessions`), and accounts still on the default password must choose a new one.

### Security
- **Chain seal** (`documents.chain_seal`): a keyed seal over each document's workflow state and the head of its log chain. Status changes, skipped steps and deleted newest log entries made directly in the database are now detected by the Active Guard and the integrity scan. Automatic freezes keep the evidence flagged until an admin resolves it.
- **Auto-resolve** only restores from a snapshot that verifies against its sealed log entry (it used to trust the unhashed snapshot). Unfreeze also restores the step and holder from that verified snapshot.
- **Rebuild Chain** refuses entries whose signature does not verify, and refuses when the live data does not match the newest entry. System log entries are now signed with an HMAC under `APP_INTEGRITY_KEY`.
- Users are **deactivated instead of deleted** (`users.deactivated_at`; log and key-history foreign keys are `ON DELETE RESTRICT`), so their signed history stays verifiable. Deactivated users are signed out and cannot sign in.
- **Forced password change** for seeded accounts and admin-set passwords, plus self-service *Change Password* (`/account/password`).
- Staff can no longer print the tracking form (with the citizen's contact details) of documents outside their department.
- Spreadsheet reports write guest text as plain text (no formula injection); report filters are whitelisted (the CSRF token is no longer stored).
- Response cache ignores URLs with query strings (unauthenticated disk-filling).
- Login runs one bcrypt check even for unknown usernames (no timing enumeration).
- `X-Powered-By` removed; the PHP development server now sends CSP and the other security headers.
- Admin debug modal inserts database values as text, not HTML.
- Backup restore and delete require the admin's PIN; every restore first saves a `pre-restore` backup and is written to the new keyed, append-only `storage/logs/system-audit.log`, and the admin is notified of how many documents changed.
- `seed:prod` refuses to wipe an existing DTS database without `--force`; seeders only clear this app's session folder.

### Fixed
- Unfreezing a declined document, or unfreezing a document a second time, left it in an impossible `processing` state.
- Officers and admins could complete another department's step, and the log credited that department.
- Admins could not assign a department when creating or editing users. Records Officers must belong to the Records Unit.
- *Freeze All* reported "Successfully frozen 0 documents" on a wrong or locked PIN.
- Backups no longer include `jobs`, `rate_limits` and `cache`; the worker moves jobs left reserved for over 30 minutes to failed jobs.
- Backup file names include the real database name; `BACKUP_KEEP` sets retention.
- Literal `<br>` in validation errors; login error said "Email" for the Username field; double-escaped "not found" message; tracking-code placeholder length; the Users role filter "All Roles" returned nothing.
- Intake finalize/decline routes limited to Records Officers, matching the intake pages.
- `scripts/concurrency_test.php` runs on Windows (worker processes via `proc_open` instead of `pcntl_fork`, released at a shared start time) and takes the signing PIN as an argument; it used a hard-coded invalid PIN and could never pass.

### Changed
- Department metrics backfill runs every 5 minutes (was 30), so statistics charts stay close to live.
- Separate rate-limit buckets for tracking lookups and status polling; styled 403/404/429 pages.
- Accessibility: dialog roles and labels, focus handling and Escape for modals, names for icon-only buttons, heading order, autocomplete hints on the guest form.
- Integrity verification code is shared (`IntegrityManager`) instead of duplicated in the scan job and debug view.

## 2026-10-12 16:45

**Version:** 1.25.0-Release+202610121645

### Changed
- Retired the Laravel codebase and moved the PHP app from `working-php/` to the repository root (commit `2b314a1`).
- NGINX now runs on **Windows and Linux** from one template (`nginx/site.conf`): Windows uses a pool of 4 `php-cgi` processes and a project-local config/certificate under `storage/`; Linux keeps PHP-FPM. Added gzip, 30-day browser caching for static assets and a 300 s FastCGI timeout.
- `composer run dev` on Windows starts NGINX + php-cgi + the queue worker (restarting the worker after its job/memory limit) and stops all of them on Ctrl+C; without NGINX it falls back to `php -S` with the new `public/router.php`. `composer run prod` now also starts the worker (`--no-worker` to opt out).
- Department metrics backfill rebuilds only the last 2 days every 30 minutes (`--full` for seeders) instead of truncating all history.
- `IntakeController`, `StatisticsController` and `GenerateReportJob` now share `DocumentQueryService` / `ReleasedReportQuery` instead of duplicated SQL.
- Real environment variables override `.env`; new `APP_DEBUG` (error display, default off) and `NGINX_PATH` keys; `seed.js` honours `APP_URL`.
- `database.sql` no longer hard-codes `deped_dts`; `seed:prod` creates and uses `DB_DATABASE`.
- Stopped tracking the 100 MB `from_dts/dts_docroutes*` dumps in git (files stay on disk); `package-lock.json` is now tracked.
- Rewrote the README and user manuals to match the PHP code; added `ARCHITECTURE.md`, `HARDWARE_SPECS.md` and `QUANTUM_SAFETY.md` for the PHP app; marked `documentation/laravel-era/` as legacy.

### Added
- `composer run migrate` (`scripts/migrate.php`): idempotent upgrade adding indexes on `jobs`, `notifications` (composite unread), `departments.name`, `purposes.name`, `documents.updated_at`, `cache.expiration`, and dropping unused Laravel tables.
- `composer run css`, `composer run seed:legacy [-- file.csv]`.
- Hourly garbage collection of expired rows in the `cache` table.

### Fixed
- Database backups and restores on Windows: the POSIX `MYSQL_PWD=… mysqldump` syntax failed under `cmd.exe`, so every backup was a 100-byte error message. New `MysqlCli` helper runs the tools without a shell and passes the DB port.
- Backup download/restore/delete returned 404 on the PHP built-in server (URLs ending in a filename).
- Officer intake date filter in `DocumentQueryService` referenced a non-existent `max_logs.handled_at` column.
- Failed-job delete buttons redirected to the non-existent `/system-health` page.
- Queue worker job claim race (now checks the claiming `UPDATE`'s row count) and "MySQL server has gone away" after idle periods.
- `composer run proxy` pointed `cloudflared` at `http://` while NGINX serves HTTPS.

### Removed
- Unused `symfony/cache` and `phpmailer/phpmailer` packages and the `SMTP_*`, `PIN_CACHE_DURATION`, `DB_CONNECTION` env keys.
- ~1 MB of unused libsodium JS (and the encrypted private key it exposed to every page), a duplicate Chart.js include, unused fonts, a per-request debug log write.
- Dead controller methods, `DocumentPolicyMiddleware`, orphaned admin views, and broken `scripts/test_*` files.
- Unused tables `job_batches`, `password_reset_tokens`, `sessions`, `cache_locks`, `prediction_keywords`.

### Performance
- Tracking-form PDF embeds a 480 px seal: PDF 568 KB → 101 KB, generation ~0.86 s → ~0.44 s.
- Tailwind CSS is built minified (61 KB → 46 KB).
- Date filters use index-friendly ranges instead of `DATE(column)`.

## 2026-07-28 00:17

**Version:** 1.24.0-Release+202607280017

### Fixed
- Consolidated scattered `.gitignore` files into a unified root configuration.

### Added
- Added screenshots to `README.md`.
- Updated documentation to point to real system export examples (PDF and XLSX).
- Added a custom non-commercial `LICENSE` file explicitly forbidding distribution and monetization.

## 2026-07-27 23:21

**Version:** 1.23.4-Release+202607272321

### Fixed
- Fixed an infinite loop bug in digital signature setup by clearing the user's personal HTML page cache in `SecurityKeyController.php` and `UserController.php`, preventing the `CacheMiddleware` from serving a stale page containing the setup modal.
- Fixed an issue where newly generated digital signatures couldn't be used immediately by updating `$_SESSION['private_key']` with the new Ed25519 private key right after generation.

### Added
- None.

## 2026-07-27 17:46

**Version:** 1.23.3-Alpha+202607271746

### Fixed
- Updated notification toast messages across document lifecycle actions to dynamically include the document tracking code (e.g. `Document DEPED-XXXXXXXX marked as completed and released.`).
- Fixed root cause in [`AuthMiddleware.php`](src/Middleware/AuthMiddleware.php) where premature `session_write_close()` calls during request middleware execution froze PHP session files and discarded controller `$_SESSION['success']` flash alerts.
- Extended [`ReleasingController.php`](src/Controllers/ReleasingController.php) and [`DocumentController.php`](src/Controllers/DocumentController.php) (`finalizeIntake`, `decline`, `release`) to query the document tracking code for toast notification consistency.
- Extended [`pjax-router.js`](public/js/pjax-router.js) and [`app.php`](src/Views/layouts/app.php) to synchronize toast modals and notification dropdown lists across client-side page swaps with console debugging (`[DTS Notif System]` and `[DTS PJAX Router]`).

### Added
- Added `getUnread()` endpoint to [`NotificationController.php`](src/Controllers/NotificationController.php) and registered `GET /api/notifications/unread` route in [`index.php`](public/index.php).

## 2026-07-27 17:16

**Version:** 1.23.2-Alpha+202607271716

### Fixed
- Re-generated `scripts/seeders/names_data.json` with 100% ASCII compliance by applying Unicode NFD normalization in `build-names-json.py`, transliterating Spanish accents and tildes (`ñ` -> `n`, `á` -> `a`, `í` -> `i`) to reflect real-world fast data entry conventions (*Garcia*, *Rodriguez*, *Fernandez*, *Lopez*, *Perez*, *Sanchez*, *Martinez*, *de Leon*, *Diaz*).
- Added a name combination blacklist (`isBlacklisted()`) and 10-attempt re-sampling loop to `fast-seed.php` and `seed.js` to filter out unusual or culturally sensitive generated name pairs (e.g. `"Mama Mary"`, `"Mary Mama"`, `"Papa Jesus"`).

### Added
- N/A

## 2026-07-27 16:59

**Version:** 1.23.1-Alpha+202607271659

### Fixed
- Fixed document back routing and browser history state corruption across [`pjax-router.js`](public/js/pjax-router.js), [`show-document.php`](src/Views/general/show-document.php), [`document-hash-chain.php`](src/Views/general/document-hash-chain.php), and [`DocumentController.php`](src/Controllers/DocumentController.php).
- Resolved duplicate `history.pushState()` calls during `popstate` events in [`pjax-router.js`](public/js/pjax-router.js) that broke `history.back()`.
- Implemented `sessionStorage` origin URL tracking (`dts_doc_origin`) to return users to their exact entry page (including active filter query parameters and table pagination state) when navigating back from document details.

### Added
- N/A

## 2026-07-27 16:46

**Version:** 1.23.0-Release+202607271646

### Fixed
- Fixed document sorting and pagination in `DocumentQueryService.php` by changing default document list ordering to `created_at DESC, id DESC` and introducing a compound cursor format (`created_at_id`) for keyset pagination with backward-compatibility for single ID cursors.
- Updated fast and Node database seeders (`fast-seed.php` and `seed.js`) to strictly preserve chronological monotonicity between generated document IDs and `created_at` timestamps across working days and peak hours.

### Added
- Integrated document state snapshotting (`document_snapshot`) into bulk log insertions inside `fast-seed.php`.
- Added user documentation guide `HASH_VERIFICATION_LAYMAN_GUIDE.md` detailing cryptographic hash chain verification, document state hashing, Ed25519 digital signatures, and genesis integrity.

## 2026-07-27 13:46

**Version:** 1.22.1-Alpha+202607271346

### Fixed
- Added missing **Document Title** (`$document['title']`) field to the Document Information metadata card in [`show-document.php`](src/Views/general/show-document.php).

### Added
- N/A

## 2026-07-27 13:42

**Version:** 1.22.0-Release+202607271342

### Fixed
- N/A

### Added
- Created `CleanupStalePendingDocumentsJob.php` to automatically transition stale pending documents lingering past 3 days without Records Office intake to `declined` status with system-sealed cryptographic log entries.
- Integrated automated 24-hour pending document garbage collection into `console.php` worker scheduler loop.

## 2026-07-27 13:04

**Version:** 1.21.0-Alpha+202607271304

### Fixed
- Replaced increment/placeholder guest emails (`guestX@example.com`, `fastY@test.com`) and 4-digit phone numbers in `fast-seed.php` and `seed.js` with realistic guest profile data.

### Added
- Added `sanitizeForEmail()`, `getRandomDomain()`, and `getRandomPerson()` methods to `WeightedNameGenerator` in `fast-seed.php` and `seed.js`.
- Implemented email handle generation supporting multiple realistic patterns (`firstname_lastname`, `lastname_firstname`, `firstnameLastname`, `lastnameBirthdate`, `firstnamebirthdate`), capping multi-word first names to the first 2 names, realistic birthdate suffixes (e.g., `march1979`, `031979`, `1979`, `79`), common domains (`gmail.com`, `yahoo.com`, `deped.gov.ph`, `outlook.com`, `hotmail.com`), and 11-digit Philippine mobile phone numbers (`09xxxxxxxxx`).

## 2026-07-27 11:14

**Version:** 1.20.0-Alpha+202607271114

### Fixed
- Replaced generic placeholder document titles (`"Fast Seeded Doc X"`, `"Automated Test Document Y"`) in `fast-seed.php` and `seed.js` with realistic DepEd (Department of Education) document titles specific to each document's purpose.

### Added
- Added `scripts/seeders/titles_data.json` containing 5 domain-appropriate document titles for each of the 23 default database purposes (115 titles total).
- Updated `scripts/seeders/seed.js` and `scripts/seeders/fast-seed.php` to fetch purpose names from the database and sample realistic titles matching each document's purpose.

## 2026-07-27 10:43

**Version:** 1.19.0-Alpha+202607271043

### Fixed
- Replaced static placeholder guest names (`"Fast Guest 1"`, `"Seeded Guest 1"`) in `fast-seed.php` and `seed.js` with believable weighted demographic name generation.
- Fixed form input loss on validation failure in `login.php` and `welcome.php`. Valid fields now retain previously entered values across redirects while invalid fields that triggered constraint errors are cleared out and highlighted with high-visibility red borders (`border-red-500 ring-2 ring-red-500/20`) and inline error messages.

### Added
- Added `scripts/seeders/build-names-json.py` to extract 1,000 forenames and 994 lastnames with population weights from `pop_names.csv` (ISO-8859-1) into a shared UTF-8 dataset `scripts/seeders/names_data.json`.
- Integrated `WeightedNameGenerator` class into `scripts/seeders/fast-seed.php` and `scripts/seeders/seed.js` using roulette-wheel sampling and a multi-word forename distribution (80% single, 18% double, 2% triple first names) to produce believable Filipino names with accented characters intact (*García*, *Rodríguez*, *Fernández*, *López*, *Pérez*, *Sánchez*, *Martínez*, *de León*).
- Added global form flash helpers (`has_error`, `old`, `error_msg`, `field_error_class`, `clear_form_flash`) in `src/helpers.php` to handle session flash inputs (`$_SESSION['old']`) and field error maps (`$_SESSION['field_errors']`).

## 2026-07-26 15:06

**Version:** 1.18.0-Release+202607261506

### Fixed
- Fixed navigation flow in `src/Views/layouts/app.php` where clicking the header logo directed authenticated users back to the public guest portal (`/`). Updated the logo link to `/dashboard` so logged-in users return to their role-specific dashboard.
- Standardized top-right header button styling in `src/Views/layouts/guest.php` using the uniform `table-filters.php` design tokens (`bg-accent-1`, uppercase tracking, focus ring, `h-[38px]`).

### Added
- Added a dedicated "Staff Sign In" (or "Dashboard" when logged in) button to the public guest layout (`guest.php`) next to the theme toggle, providing administrative staff and officers with a direct login path from the guest landing page.
- Added multi-stakeholder pitch documentation (`documentation/PITCH_AND_VALUE_PROPOSITION.md`) featuring targeted technical value propositions for IT Administrators, Government Decision Makers, Cryptographers/Auditors, Office Workers, and General Citizens.

## 2026-07-26 12:06

**Version:** 1.17.7-Alpha+202607261206

### Fixed
- Fixed an OOM risk in `IntegrityCheckJob.php` where `$mismatchedIds` was an unbounded PHP array appended to on every corrupted log entry, with O(n) `in_array()` deduplication calls that caused O(n²) scan time on large datasets. Replaced with an associative-array hashset (`$mismatchedIdsSet`) for O(1) duplicate detection. Introduced `$mismatchedIdsList` (capped at `MAX_TRACKED_MISMATCHES = 1000`) for safe admin display, and `$mismatchedIdsCount` as a separate accurate total counter so the verified-percentage metric remains correct even when mismatches exceed the cap. Applied the same cap to `$mismatchedDocumentTrackingCodes`.
- Fixed an unbounded disk-growth issue where the `cache/responses/` directory accumulated stale HTML response-cache files indefinitely. Expired files were skipped on serve but never deleted. Added a 4th scheduled task to `console.php`'s internal worker scheduler that runs a garbage collector every hour, purging all `.html` files older than 1 hour (well past the 55s serve TTL).
- Fixed an unbounded disk-growth issue with `storage/logs/navigation.log`, which was appended to on every request with no rotation. The new hourly GC task now archives the log to a timestamped file (e.g., `navigation-2026-07-26-120000.log`) when it exceeds 50 MB.

### Added
- Added `$lastGcTime` timestamp variable and the 4th argument `int &$lastGcTime` to `runScheduledTasks()` in `console.php` to support the new cache GC and log rotation scheduled task.

## 2026-07-26 11:31

**Version:** 1.17.6-Alpha+202607261131

### Fixed
- Fixed an issue in `console.php` where background jobs (like `CreateBackupJob` and `GenerateReportJob`) were processed sequentially in a single-threaded loop, causing bottlenecks during long-running tasks. Refactored the queue worker into a master dispatcher that spawns parallel child processes via `runner.php` up to a configurable concurrency limit (`$maxWorkers = 2`), ensuring true asynchronous background processing.
- Re-ordered columns in the `idx_log_category` composite index in `database.sql` to `(user_id, action_category, document_id, created_at)`. This enables MySQL to perform a "Loose Index Scan" (Skip Scan) on the `GROUP BY document_id` queries inside `DocumentQueryService`, reducing execution time for high-volume users from 1.2s to under 0.4s.

### Added
- Integrated the queue worker directly into the local development server lifecycle. `scripts/dev.php` now seamlessly starts `console.php` in the background (piping output to `storage/logs/worker.log`) and auto-cleans orphaned worker instances upon restart.

## 2026-07-25 20:25

**Version:** 1.17.5-Alpha+202607252025

### Fixed
- Fixed a massive performance bottleneck on `/intake` and `/tasks/completed` pages that caused loading to consistently take ~600ms to over 2 seconds. The underlying SQL query utilized the `ROW_NUMBER() OVER()` window function to filter for the latest log entry per document, which forces MySQL to construct and sort large temporary tables. Refactored the queries in `IntakeController.php` and `DocumentQueryService.php` to use a much more efficient `MAX(created_at) ... GROUP BY document_id` approach. This optimization leverages the covering index and reduces the data query execution time from ~600ms down to 0.05ms, resulting in instantaneous page transitions.

## 2026-07-25 20:10

**Version:** 1.17.4-Alpha+202607252010

### Fixed
- Fixed a major race condition in `pjax-router.js` where page-specific scripts (like `statistics.js` and `system-health.js`) would fail to initialize on the very first PJAX visit to a page, but mysteriously work on the second visit. The router was dispatching the `dts:page-loaded` event synchronously, but the browser was fetching external `<script>` tags asynchronously. This caused the event to fire *before* the script had loaded and registered its listener. Removed all `dts:page-loaded` listeners across all 10 view JS files and replaced them with inline `document.readyState` checks, guaranteeing exactly-once, zero-latency execution on every navigation.

## 2026-07-25 19:41

**Version:** 1.17.3-Alpha+202607251941

### Fixed
- Fixed an issue where graphs on `statistics.php` and `system-overview.php` would randomly fail to render after PJAX navigation. Chart.js maintains an internal global registry (`Chart.instances`) of all created charts. When navigating back to these pages, new `<canvas>` elements were generated with identical IDs, but the old chart instances still existed in memory. This caused Chart.js to silently throw "Canvas is already in use" errors. Resolved by iterating through `Chart.instances` and forcefully destroying all charts in `pjax-router.js` immediately before executing the DOM swap.
- Patched a memory and network leak in `statistics.js` where the 60-second `setInterval` data poller was never cleared upon PJAX navigation, causing multiple concurrent polling intervals to stack and spam the server with redundant requests. It is now properly wired to abort alongside the global PJAX controller.

## 2026-07-25 19:23

**Version:** 1.17.2-Alpha+202607251923

### Fixed
- Fixed a visual bug in `pjax-router.js` where the active navigation indicator would sometimes appear grey or duplicate (e.g. both "Tasks" and "Completed" highlighting simultaneously). This happened because the client-side router used a flawed string-matching algorithm (`startsWith`) and failed to perfectly mirror the complex Tailwind class permutations defined in PHP. The client-side logic has been removed entirely; `app.php` now wraps the navigation links in `<div id="pjax-nav-links">`, which the router swaps dynamically on every navigation to guarantee exact replication of the PHP server-rendered active states.

## 2026-07-25 19:15

**Version:** 1.17.1-Alpha+202607251915

### Fixed
- `pjax-router.js` + `app.php`: page heading (`<header id="pjax-header">`) was not being swapped after navigation because it sat outside `#pjax-content`. Added `id="pjax-header"` to the layout element (with an empty hidden placeholder for pages without a heading) and updated the router to swap both `#pjax-header` and `#pjax-content` on every navigation.
- Converted all remaining inline `DOMContentLoaded` wrappers across officer, staff, admin, and shared partials to named functions that also listen to `dts:page-loaded`. Without this, navigating via PJAX to officer/staff pages left all action buttons (release, complete, sign, QR scan) completely unresponsive: `officer/releasing.php` → `initReleasingPage()`, `officer/manage-documents.php` → `initManageDocumentsPage()`, `admin/system-overview.php` → `initSystemOverviewPage()`, `staff/tasks.php` → `initStaffTasksPage()`, `partials/scan-qr-modal.php` → `initScanQrModal()`, `partials/signing-modal.php` → `initSigningModal()`, `general/show-document.php` → `initShowDocumentPage()`.

## 2026-07-25 18:56

**Version:** 1.17.0-Alpha+202607251856

### Fixed
- `AuthMiddleware.php`: added `session_write_close()` immediately after reading auth session data to release the PHP session file lock early, eliminating request head-of-line blocking where a slow `/admin-dashboard` chart API call would prevent a concurrent navigation request from starting.

### Added
- `public/js/pjax-router.js`: new PJAX client-side router implementing instant navigation. Intercepts `<a>` clicks, fetches the target page via `fetch()`, parses it with `DOMParser`, and swaps only `#pjax-content` without a full document reload. Manages a shared `AbortController` (`window.__pjaxController`) that cancels all in-flight chart and polling API calls when the user navigates away. Fires `dts:page-loaded` CustomEvent after every swap as a page lifecycle hook.
- `src/Views/layouts/app.php`: added `#pjax-progress-bar` (3px gradient top bar) for visual navigation feedback, `#pjax-content` wrapper div as the PJAX swap target, and loads `pjax-router.js` last in `<body>`.
- Refactored `admin-dashboard.js`, `statistics.js`, and `system-health.js`: wrapped all init logic in named exported functions (`initAdminDashboard`, `initStatistics`, `initSystemHealth`) listening to both `DOMContentLoaded` and `dts:page-loaded` so charts and event bindings re-initialize correctly after PJAX navigation. All `fetch()` calls now carry `window.__pjaxController.signal` for automatic cancellation on navigation; `AbortError` is silenced in catch handlers.

## 2026-07-25 18:34

**Version:** 1.16.14-Alpha+202607251834

### Fixed
- Updated UI styling and layout responsive alignment for database performance metrics in [system-overview.php](src/Views/admin/system-overview.php).
- Refactored background job queue processors ([CreateBackupJob.php](src/Jobs/CreateBackupJob.php), [GenerateReportJob.php](src/Jobs/GenerateReportJob.php), [RestoreBackupJob.php](src/Jobs/RestoreBackupJob.php)) and seeder digital key reset routines ([fast-seed.php](scripts/seeders/fast-seed.php), [seed.js](scripts/seeders/seed.js)).

### Added
- Integrated "Average Turnaround Time (TAT) by Department" detailed view modal in [dashboard.php](src/Views/admin/dashboard.php) powered by [admin-dashboard.js](public/js/admin-dashboard.js).
- Added departmental load vs time and database performance metrics calculations in [AdminAnalyticsService.php](src/Services/AdminAnalyticsService.php) and [DatabasePerformanceService.php](src/Services/DatabasePerformanceService.php).

## 2026-07-23 16:38

**Version:** 1.16.13-Alpha+202607231638

### Fixed
- Standardized document navigation back URL resolution in `DocumentController` (`$_SESSION['doc_return_url']`) and added smart `history.back()` behavior to the "Back to Document" header link in [document-hash-chain.php](src/Views/general/document-hash-chain.php).
- Capped duration calculations in `AdminAnalyticsService::getProcessingHotspotsData` to prevent overflow and negative time intervals.

### Added
- Added "Peak Intake Hours" bar chart telemetry on the Admin Dashboard (`/api/admin-dashboard/peak-intake-hours`) to visualize document submission distribution across 24-hour time slots.
- Integrated one-click copy buttons for cryptographic hashes in [document-hash-chain.php](src/Views/general/document-hash-chain.php) and exception stack traces in [system-overview.php](src/Views/admin/system-overview.php).

## 2026-07-23 14:28

**Version:** 1.16.12-Alpha+202607231428

### Fixed
- Refactored [CRYPTOGRAPHIC_LOGS_EXPLAINED.md](documentation/CRYPTOGRAPHIC_LOGS_EXPLAINED.md) from dialogue format into a low-level → high-level → key-takeaways explanation; updated the root README documentation blurb to match.

### Added
- None

## 2026-07-23 14:23

**Version:** 1.16.11-Alpha+202607231423

### Fixed
- None

### Added
- Added [CRYPTOGRAPHIC_LOGS_EXPLAINED.md](documentation/CRYPTOGRAPHIC_LOGS_EXPLAINED.md): IT debate covering hash-chain provenance (birth-certificate analogy), why cryptographic custody is not overengineering, and cryptography vs encryption; linked from root README System Documentation.

## 2026-07-23 09:58

**Version:** 1.16.8-Alpha+202607230958

### Fixed
- Removed redundant subfolder README files (`README.md` and `backup-laravel/README.md`) to establish the root [README.md](README.md) as the single master documentation file for the repository.

### Added
- None

## 2026-07-23 09:45

**Version:** 1.16.7-Alpha+202607230945

### Fixed
- Expanded and clarified database seeder usage (`seed.js`, `scripts/fast-seed.php`, `scripts/setup-production-db.js`) in [README.md](README.md) with parameter definitions, composer command aliases (`seed:dev`, `seed:fast`, `seed:prod`), and performance characteristics.

### Added
- Added a comprehensive Script Directory Guide table in [README.md](README.md) detailing all administrative, telemetry, tuning, security tampering (`corrupt.php`), key generation, and testing utilities in `scripts/`.
- Included Nginx configuration reverse proxy reference notes matching `nginx/site.conf`.

## 2026-07-23 09:27

**Version:** 1.16.6-Alpha+202607230927

### Fixed
- Updated [README.md](README.md) to document the transition from host `cron` daemons to the unified container-compatible [console.php](console.php) background worker & scheduler.

### Added
- Added System Documentation & Manuals section in [README.md](README.md) referencing the role-based manuals ([admin_manual.md](documentation/user_manuals/admin_manual.md), [officer_manual.md](documentation/user_manuals/officer_manual.md), and [staff_manual.md](documentation/user_manuals/staff_manual.md)).

## 2026-07-23 09:23

**Version:** 1.16.5-Alpha+202607230923

### Fixed
- N/A

### Added
- Added comprehensive role-based Markdown user manuals for System Administrators ([admin_manual.md](documentation/user_manuals/admin_manual.md)), Records Officers ([officer_manual.md](documentation/user_manuals/officer_manual.md)), and Department Staff ([staff_manual.md](documentation/user_manuals/staff_manual.md)) detailing first-time login, security PIN key generation, RBAC permissions, document workflow execution, and JIT Active Guard tamper recovery.

## 2026-07-23 09:04

**Version:** 1.16.4-Alpha+202607230904

### Fixed
- Integrated an internal task scheduler loop into `console.php` (`runScheduledTasks`) to run telemetry sampling (`sample-db-metrics.php`), departmental TAT backfill (`backfill-metrics.php`), and metric rollups (`rollup-metrics.php`) automatically inside the console worker loop, eliminating reliance on host `cron` daemons in Distrobox/containerized environments.

### Added
- None

## 2026-07-23 08:54

**Version:** 1.16.3-Alpha+202607230854

### Fixed
- Updated production documentation in `README.md` to specify crontab schedules for metric sampling, departmental TAT backfilling, and metric rollups.

### Added
- Added production database telemetry sampler script `scripts/sample-db-metrics.php` to periodically log active MySQL connection threads (`Threads_connected`), query latency, and slow query counts into `database_metrics`.

## 2026-07-23 08:43

**Version:** 1.16.2-Alpha+202607230843

### Fixed
- Removed all `||`, `??`, and `?:` fallback operators from database connection setups across `seed.js`, `scripts/setup-production-db.js`, `scripts/tune-database.php`, and `src/Config/config.php` to strictly require environment variables supplied by `.env`.

### Added
- None

## 2026-07-23 08:39

**Version:** 1.16.1-Alpha+202607230839

### Fixed
- Fixed `.env` parsing across `seed.js` and `scripts/setup-production-db.js` by explicitly defining the target `.env` file path relative to script directory (`path.join(__dirname, '.env')` / `path.join(__dirname, '../.env')`), preventing `dotenv` from failing when commands are launched from different current working directories.
- Removed all hardcoded database password fallback strings from `seed.js`, `scripts/setup-production-db.js`, and `scripts/tune-database.php` to enforce secure environment variable resolution.
- Refactored `tune-database.php` environment parser to handle unquoted key-value pairs without breaking on special characters.

### Added
- None

## 2026-07-23 08:34

**Version:** 1.16.0-Alpha+202607230834

### Fixed
- Fixed empty Departmental Average TAT, Average TAT by Department, and Department Drill-Down analytics by embedding the `backfill-metrics.php` execution and cache clearing directly into `scripts/fast-seed.php`.
- Corrected non-working day calculation logic in `scripts/fast-seed.php` (`skipNonWorkingDays`) and `seed.js` (`skipWeekend`) to treat Friday as a valid working day instead of skipping it.
- Improved processing time computation in `scripts/backfill-metrics.php` to calculate exact turnaround time (TAT) per step per department directly from `document_logs` timestamp intervals.

### Added
- Added fallback database credentials in `seed.js` to match project configuration defaults.

## 2026-07-23 08:15

**Version:** 1.15.9-Alpha+202607230815

### Fixed
- Filtered out `ready_for_release` documents from the `/tasks` table query (`DocumentQueryService::getPaginatedStaffTasks`) so that finished documents ready for release are only shown in the `/releasing` view rather than appearing in active department task queues where completing them would trigger workflow errors.
- Updated `/tasks` view filter options in `src/Views/staff/tasks.php` to remove the obsolete `ready_for_release` status dropdown selection.

### Added
- Added a `Document Title` column to the "Documents Ready for Release" table in `src/Views/officer/releasing.php`.

## 2026-07-22 17:20

**Version:** 1.15.8-Alpha+202607221720

### Fixed
- Changed default user account email format in `scripts/setup-production-db.js` to clean department usernames (e.g. `admin`, `records.unit`, `cash.unit`).
- Removed strict `email` format validation rule from `AuthController::login` and `UserController::store`/`update` so non-email department usernames (e.g. `cash.unit`) pass backend validation.
- Updated `src/Views/auth/login.php`, `create.php`, and `edit.php` input controls from `type="email"` to `type="text"` and updated label to "Username / Email".
- Updated default credentials reference table in `README.md`.

### Added
- None

## 2026-07-22 16:44

**Version:** 1.15.7-Alpha+202607221644

### Fixed
- Removed unused `storage/database.sqlite` placeholder file.
- Replaced corrupted favicon generator output files with direct PNG favicon reference pointing to `/images/logoipsum-411.png` across layout templates (`login.php`, `app.php`, `guest.php`).

### Added
- None

## 2026-07-22 16:17

**Version:** 1.15.6-Alpha+202607221617

### Fixed
- Verified XLSX report generation in `GenerateReportJob.php` and removed unused `phpoffice/phpspreadsheet` dependency from `composer.json` in favor of high-performance streaming library `openspout/openspout`.

### Added
- None

## 2026-07-22 16:11

**Version:** 1.15.5-Alpha+202607221611

### Fixed
- Reorganized standalone scratch scripts (`test_cache.php`, `test_spout.php`, `test_spout2.php`, `test_spout.xlsx`, `seed_test.js`) by moving them into `scripts/` and updating autoload requirements.
- Pruned dead commented-out route block (`/tasks` index test alias) in `public/index.php`.

### Added
- Added comprehensive inline documentation across core security and architectural files (`SecurityHelper.php`, `AuthMiddleware.php`, `RoleMiddleware.php`, `DocumentPolicy.php`, `Router.php`) following context-scaled commenting principles.

## 2026-07-22 15:57

**Version:** 1.15.4-Alpha+202607221557

### Fixed
- Fixed broken `/dashboard` route by replacing missing `OfficerController` reference with a role-based redirect closure (`officer` → `/intake`, `staff` → `/tasks`, `admin` → `/admin-dashboard`).
- Resolved duplicate cache implementations by unifying `AdminDashboardController` onto `App\Core\Cache`, adding `clear()` method, and removing unused `src/Utils/Cache.php`.
- Added missing `AuthMiddleware` and `RoleMiddleware:admin` protection to `/api/admin-dashboard/current-load`.
- Extracted monolithic inline SQL from `AdminDashboardController`, `DashboardController`, and `StatisticsController` into dedicated service classes `AdminAnalyticsService` and `DepartmentAnalyticsService`.
- Fixed `StatisticsController::index` query duplication by delegating to `DocumentQueryService::getPaginatedStatistics`.
- Hardened PHP session settings (`cookie_httponly`, `cookie_secure`, `cookie_samesite=Strict`, `use_strict_mode`) in `public/index.php`.
- Resolved duplicate `/statistics` route definition in `public/index.php`.
- Converted `/integrity-monitor` into a 301 permanent redirect pointing to canonical `/all-documents`.
- Added `version` property to `Document` model and `email_verified_at`/`remember_token` properties to `User` model for 100% schema parity.
- Cleaned up obsolete TF-IDF prediction files (`RoutePredictionService.php`, `UpdateKeywordWeights.php`).

### Added
- Created global HTML escaping helper `e()` in `src/helpers.php` (autoloaded via `composer.json` and `public/index.php`) for XSS mitigation.
- Added `DocumentPolicyMiddleware.php` to allow declarative route-level policy middleware execution.
- Added centralized exception handling in `Router::dispatch()` to catch unhandled errors, log details, and display user-friendly notices or JSON responses.
- Added RESTful HTTP verb aliases (`PUT /users/{id}`, `DELETE /users/{id}`) in `public/index.php`.

## 2026-07-22 13:41

**Version:** 1.15.3-alpha+202607221341

### Fixed
- Fixed an `Invalid parameter number` PDO exception in `NotificationService.php` by uniquely naming duplicate PDO parameter bindings.
- Fixed a report generation crash in `GenerateReportJob.php` by updating `Row::fromValues` to `Row::fromValuesWithStyle` to align with the latest OpenSpout library requirements.
- Standardized the text color of the "Submitter Name" column in the Released Documents History table to match the "Title" column in the dark theme.
- Filtered out `in_transit` documents from the Staff Tasks table to ensure users only see documents that have been officially received.
- Removed the "All Departments" option from the department drill-down chart on the admin dashboard to enforce a strict department-specific view.

### Added
- None

## 2026-07-21 17:29

**Version:** 1.15.2-alpha+202607211729

### Fixed
- Fixed the subway tracking design in the guest portal (`/track`) by restructuring the UI into a 4-column responsive grid with a snaking layout, isolating elements from colliding.
- Added visual directional arrows (tails and heads) to the subway tracking map to clearly signify the start and end of document routes.
- Resolved an issue in both `scripts/fast-seed.php` and `seed.js` where declined documents were not assigned a `declined_at` timestamp, causing the Decline Rate Trends chart on the Admin Dashboard to inaccurately appear empty.
- Included the "Others" purpose into the default DB generation array inside `scripts/setup-production-db.js`, allowing seeders to properly distribute it across analytics hotspots.
- Cleaned up obsolete `SecurityHelper::cachePin()` and `clearCachedPin()` calls left over in `TaskController` and `ReleasingController`.

### Added
- None

## 2026-07-20 17:45

**Version:** 1.15.1-alpha+202607201745

### Fixed
- Completely removed the "return-requests" feature from the codebase (routes, controllers, views, layout notifications, JavaScript admin charts, service mutations, and database seeders) as it is highly unlikely to be used in a real-world workflow.

### Added
- None

## 2026-07-20 16:45

**Version:** 1.15.0-alpha+202607201645

### FIXED
- Optimized large document count queries in `IntakeController` and `DashboardController` by replacing O(N^2) correlated `NOT EXISTS` subqueries with linearly scalable `ROW_NUMBER()` CTEs.
- Fixed the Records Officer's "Documents Received" and "Average Processing Time" graphs by accurately attributing 'Intake' and 'Releasing' actions in `backfill-metrics.php`.
- Clarified the document path UI in `show-document.php` to accurately map a 6-step lifecycle that includes both 'Records Unit (Intake)' and 'Records Unit (Processing)'.
- Removed the default test credentials from the login page UI.

### ADDED
- Implemented automatic digital signature clearing at the end of both `seed.js` and `scripts/fast-seed.php`. Seeder-generated signatures remain valid, while forcing users to initialize their PIN on their first login.

## 2026-07-17 21:05

**Version:** 1.14.4-alpha+202607172105

### ADDED
- Completed an extensive codebase documentation pass. Added inline comments explaining the purpose and logic of critical, moderately complex code blocks across services, core components, controllers, middleware, policies, scripts, jobs, and models.

## 2026-07-17 20:40

**Version:** 1.14.3-alpha+202607172040

### ADDED
- Migrated database credentials out of `config.php` and into `.env` file for secure configuration.
- Formally adopted Optimistic Locking for database transactions by renaming `executeWithLock` to `executeInTransaction` and clarifying service documentation.

### FIXED
- Removed rate limiting logic from the Guest portal, ensuring reliable operations inside LAN-only environments.
- Removed deprecated memory-based PIN caching methods from `SecurityHelper` and cleaned up their lingering usages.
- Updated `seed.js` database connection keys to dynamically map to the new `.env` schema.

## 2026-07-15 22:43

**Version:** 1.14.2-alpha+202607152243

### ADDED
- Added a "Freeze All" button to the System Health Overview for administrators to batch-freeze all documents with flagged integrity issues.

### FIXED
- Fixed a bug where `DocumentWorkflowService::autoResolveDocument()` crashed with a MySQL 3140 Invalid JSON text error (`The document is empty`) by correctly mapping empty strings to `null` for the `guest_info` and `finalized_route` columns when restoring snapshots from the genesis block.
- Removed unused and obsolete file `src/Views/profile/edit.php`.
- Removed obsolete mass-migration scripts (`rewrite_dashboard.php`, `rewrite_intake.php`, `rewrite_integrity.php`, `rewrite_statistics.php`) from the `scripts/` directory as the offset-to-cursor pagination migration is complete.

## 2026-07-15 15:16

**Version:** 1.14.0-alpha+202607151516

### ADDED
- Implemented Cursor-Based Pagination and 5-minute table count caching to significantly improve loading times for massive document tables.
- Added Optimistic Locking with a `version` column to handle concurrent modifications properly with UI toast alerts instead of database row locks.

### FIXED
- Fixed a bug where `IntegrityManager` wrongly flagged valid private keys as corrupted due to AES PKCS7 padding length mismatches.
- Added `notifications` table drop statement to `database.sql` to prevent production seeding from failing.
- Fixed UI components that crashed because they were hardcoded to use offset-based pagination instead of supporting `CursorPaginator`.

## 2026-07-14 22:35

**Version:** 1.13.1-alpha+202607142235

### FIXED
- Fixed CSRF Misconfigurations by properly injecting `<meta name="csrf-token">` and patching `fetch` AJAX headers.
- Fixed weak Database Encryption architecture by upgrading Ed25519 private key storage to use Argon2id KDF and AES-256-CBC random IVs.
- Fixed insecure PIN caching vulnerability (Session Hijacking) by migrating the memory cache from the server into the browser's `sessionStorage`.
- Fixed Application-Level DoS via Custom Purposes by normalizing custom inputs to a single "Others" database record and appending specific text to document titles.
- Fixed Canonicalization / Hash Collision Injection attack in the tamper-evident chain by using strict `json_encode` instead of ambiguous pipe `|` delimiters.
- Fixed seeder scripts (`seed.js`, `generate-keys.php`) to natively understand the upgraded block hash algorithms and Argon2id KDF without breaking the rate-limiting rules.

## 2026-07-09 13:34

**Version:** 1.12.8-alpha+202607091334

### ADDED
- Ported the "Generate Report" functionality for released documents from Laravel to raw PHP, including UI integration with progress modal and dynamic SQL filtering.

### FIXED
- Fixed file download path bug where generated reports were attempting to be downloaded from the root directory instead of the `storage/app/` folder.
- Fixed an issue where the "Generate Report" button and progress bar were invisible in light mode due to using non-compiled Tailwind classes (`bg-blue-600`), replacing them with standard theme variables (`bg-accent-1`).

## 2026-07-08 22:52

**Version:** 1.12.7-Alpha+202607082252

### ADDED
- Implemented route adaptation allowing the system to update a purpose's generic route based on Records Officer actions, while carefully ignoring dynamically injected guest-specific departments.
- Added a visual tag/badge to the `/manage-documents` UI to clearly distinguish guest-selected units from the default suggested route steps.
- Ensured all relevant guest intake fields, specifically the Phone Number, are strictly required prior to submission.

### FIXED
- Fixed a major missing data issue where production seeders failed to insert document requirements and suggested routes into the `purposes` table.
- Fixed a conflict where saving a dynamically injected guest department into a route would accidentally override the purpose's permanent template.

## 2026-07-08 21:22

**Version:** 1.12.6-Alpha+202607082122

### ADDED
- Implemented `CacheMiddleware` to handle HTTP Response Caching for resource-heavy pages (`/admin-dashboard`, `/statistics`) and the guest welcome page (`/`), significantly reducing database load on high-traffic routes.
- Added localized cache clearing via `clearPersonalCache()` in `SystemHealthController`, allowing users to refresh their own view's cache without triggering a global cache drop.
- Added "Clear My Cache" buttons to the Admin Dashboard and Statistics pages.
- Added PDF Document Tracking Form generation utilizing `dompdf` and dynamic QR codes (`chillerlan/php-qrcode` v6.x).
- Linked the `/success` page to download the newly generated tracking form.

### FIXED
- Updated the "Print Document Tracking Form" button on the guest success page to correctly generate a downloadable PDF rather than attempting a raw HTML print.
- Ensured caching middleware safely bypasses caching when active flash messages (errors/successes) are present in the session to avoid caching temporary states.

## 2026-07-08 15:14

**Version:** 1.12.5-Alpha+202607081514

### ADDED
- None

### FIXED
- Fixed a bug in `IntegrityCheckJob.php` where Ed25519 (Libsodium) signatures were incorrectly being verified using OpenSSL RSA functions.
- Corrected the system signature validation prefix in `IntegrityCheckJob.php` from `MOCK_SIG:` to `SYSTEM_SIG:` to properly align with `IntegrityManager` defaults.

## 2026-07-08 14:54

**Version:** 1.12.4-Alpha+202607081454

### ADDED
- Introduced a reusable `modal.php` component for standardized dialogs across the application.
- Added comprehensive security architecture documentation (`dts_security_architecture.md`).
- Included initial `database.sql` and `scripts/setup-production-db.js` for production database setup.
- Added document freeze and unfreeze capabilities in the document details view for administrators.
- Added default test credentials helper in the login view for easier testing and demonstration.

### FIXED
- Updated various admin views and layout files to integrate with the new `modal.php` component and enhance the system health dashboard.
- Modified `SystemHealthController`, `IntegrityManager`, and `DocumentWorkflowService` to support document freezing and manual overrides.
- Updated `AuthMiddleware` and `seed.js` for enhanced security handling and database seeding.

## 2026-07-08 14:51

**Version:** 1.12.3-Alpha+202607081451

### ADDED
- Added a confirm password field to the security key setup modal and standalone view to ensure users type their PIN correctly.
- Added server-side validation in `SecurityKeyController` to verify that the confirm PIN matches the main PIN before generating the security key.

### FIXED
- None

## 2026-07-07 21:10

**Version:** 1.13.0-alpha+202607072110

### ADDED
- Implemented a centralized `DocumentWorkflowService` utilizing raw PDO transactions (`SELECT ... FOR UPDATE`) to manage complex, concurrent document lifecycle operations safely.
- Ported and integrated the document decline logic (`/documents/decline`) into the records officer intake phase, ensuring an unbroken audit trail for declined documents.
- Diversified `seed.js` generator to produce full-spectrum document lifecycle states (Pending, Processing, Declined, Released), district varieties, and purpose hotspots for rigorous analytics load testing.

### FIXED
- Removed 10 obsolete model and controller files to significantly reduce legacy framework-style bloat in favor of the new centralized database service.
- Fixed the Document Status Distribution chart on the Admin Dashboard to dynamically mount and display counts for all 7 lifecycle statuses, preventing zeroes from disappearing from the UI.
- Reverted the records officer intake page (`manage-documents.php`) back to using a raw HTML decline modal to prevent crashes following the deliberate teardown of the global reusable `modal.php` component.
- Repaired a missing closing bracket syntax parsing error inside `DocumentController.php` that was blocking the document timeline view.

## [1.12.0-Alpha+202607062231] - 2026-07-06

### ADDED
- **Pure PHP Port:** Completely ported the Laravel backend to pure Vanilla PHP, eliminating framework overhead while maintaining MVC architecture and database integrity.
- **Component UI Architecture:** Introduced highly reusable PHP view components (`table.php`, `pagination.php`, `data-panel.php`, `qr-scanner.php`) to mirror modern frontend framework paradigms.
- **Global Typography:** Integrated and enforced the "DM Sans" font family across the entire application interface.
- **Custom Branding:** Added a custom high-resolution logo image across all application headers and resolved missing browser favicons.

### CHANGED
- **Repository Structure:** Moved the `CHANGELOG.md` to the project root to serve as the unified timeline, and created a dedicated `README.md` for the `working-php` project.
- **UI Personalization:** Refactored dashboard page headers and inner panels to dynamically render the logged-in user's department name instead of generic placeholder text.
- **CSS Theming:** Extracted hardcoded Tailwind colors into centralized CSS variables to ensure seamless light/dark mode transitions across all tables, headers, and components.

## [1.11.1-Alpha+202607011311] - 2026-07-01

### ADDED
- **Repository Structure:** Created `backup-laravel` and `working-php` directories to prepare for the pure PHP rebuild of the DTS system.

### CHANGED
- **Project Relocation:** Moved all existing Laravel project files (excluding the `.git` repository) into the `backup-laravel` directory to serve as a functional reference application.

---

> **Laravel era (v0.1.0 – v1.11.0).** Everything below describes the retired Laravel 12 version (Artisan, Vite, Blade, local-ssl-proxy, return requests, TF-IDF routing). It is kept as history; none of it applies to the current PHP app.

## [1.11.0-Alpha+202607011146] - 2026-07-01

### FIXED
- **Navigation Visual Bug:** Fixed an issue where the Staff role showed both Dashboard and Tasks as active simultaneously. 
- **Code Duplication:** Consolidated Officer and Staff task routes, controllers, and views into a unified codebase following the DRY principle.

### ADDED
- **Security Key Modal:** Added a "Confirm Secret Signing PIN" field to the Digital Signature Initialization modal to prevent user typos.

## [1.10.2-Alpha+202603232140] - 2026-03-23

### FIXED
- **Return Request Workflow:** Corrected the document status when a return request is processed, ensuring the document is set back to `in_transit` to the requesting department.
- **Task Completion Logic:** Updated `TaskController` to correctly handle the transition to `ready_for_release` or `in_transit` based on whether there are more steps, ensuring the document is never left in an ambiguous state after completion.
- **Model Lifecycle Hooks:** Refactored `DocumentLog` model to use the modern `booted()` method instead of `boot()`, aligning with Laravel 12 best practices.
- **Metric Update Robustness:** Enhanced the `MetricUpdateService` to use a more stable `updateOrInsert` followed by explicit `increment` calls, preventing potential race conditions and ensuring accurate analytics across different database drivers.

### ADDED
- **High-Concurrency Testing Suite:** Introduced `tests/Feature/ConcurrencyTest.php` to verify system behavior under simultaneous user actions.
- **Comprehensive Route Testing:** Added `tests/Feature/DocumentRoutesTest.php` for exhaustive verification of document lifecycle transitions and role-based routing.
- **Composer Test Shortcuts:** Added `full_test` and `specific_test` scripts to `composer.json` for streamlined execution of the new test suites.

### CHANGED
- **Test Suite Modernization:** Relocated and refactored core integrity tests into the standard `tests/Feature` directory, replacing the legacy `tests/Integrity/IntegrityCheckTest.php` for better alignment with standard Laravel testing patterns.

## [1.10.1-Alpha+202603221000] - 2026-03-22

### FIXED
- **Error Page Accessibility:** Completely overhauled the `403.blade.php` error page to resolve long-standing text visibility issues in both light and dark themes. Replaced redundant Bootstrap card structures with a clean, centered Tailwind design.
- **UI Component Standardization:** Integrated the project's own `x-secondary-button` and Tailwind theme-aware text classes into the 403 error page for visual consistency with the login and guest portals.

### ADDED
- **Administrative Self-Safeguards:** Implemented the **'Never Leave the Cockpit Empty'** rule. Administrators are now programmatically and physically prevented from deleting their own accounts or demoting their own roles via the UI and controller, ensuring the system always has an authorized pilot.
- **Governance Narratives:** Enriched `paper/PITCH_SCRIPT.md` with expert narratives on administrative fail-safes and the "Endless Extensibility" of the User=Department architecture.
- **Self-Editing Feedback:** Added clear UI notices and disabled states in the User Edit view to inform administrators when certain options are restricted during self-editing.

### CHANGED
- **Administrative UX:** Relocated the "Delete User" action behind a role-aware check in the Edit view, providing a safer and more centralized management workflow.

## [1.10.0-Alpha+202603202230] - 2026-03-20

### FIXED
- **Critical Integrity Synchronization:** Standardized system-initiated signatures (Freeze/Unfreeze/System Actions) to utilize the `MOCK_SIG` format within the `DocumentLog` boot logic. This ensures administrative logs are cryptographically verifiable while remaining distinct from real PIN-signed actions.
- **Hash Formula Delimiter Consistency:** Patched the `RebuildHashChain` command to include missing pipe (`|`) delimiters, aligning the "Self-Healing Ledger" logic with the production hashing formula to prevent chain corruption during repairs.
- **Audit Pagination Reliability:** Removed redundant `orderBy` clauses from the `VerifyIntegrityChain` command and `IntegrityCheckJob`. This ensures `chunkById` functions correctly across massive datasets, preventing records from being skipped during large-scale integrity audits.
- **Nomadic Navigation Logic:** Refactored the "Back" button safety check in `DocumentController` to utilize host-matching instead of literal `APP_URL` comparison. This resolves the redirection "trap" for users accessing the system via nomadic mDNS hostnames (e.g., `.local`) on mobile devices.
- **Contextual Redirection:** Integrated missing `back_to` query parameters into the System Health Monitor and Integrity Monitor views to ensure a seamless "View Details -> Back to List" user experience.

### ADDED
- **Full-Spectrum Organizational Simulation:** Expanded the `PurposeSeeder` with 6 new categories and 8+ new document purposes. This ensures that the high-fidelity simulation now generates data for all 14 departments, providing a complete picture of the Division's operations.
- **Comprehensive Analytics Transparency:** Updated the "Average TAT by Department" chart to include all departments—including those with zero activity—via a `LEFT JOIN` strategy. 
- **Technical Pitch Documentation:** Enriched the `paper/PITCH_SCRIPT.md` with technical deep-dives into the 7-variable hash formula, the "Self-Healing Ledger" mechanics, and the temporal realism of the simulation engine.

### CHANGED
- **Analytics Performance Sorting:** Reconfigured the Average TAT charts to strictly order departments from the **lowest (fastest)** to the **highest (slowest)** turnaround time, with an alphabetical secondary sort for organizational clarity.

## [1.9.5-Alpha+202603202150] - 2026-03-20

### CHANGED
- **Added hostname:** Added (`--hostname 0.0.0.0`) to composer proxy.
- **Added DRAFTS:** Added proposal paper drafts.
- **Updated Documentation:** Updated ARCHITECTURE.md and HARDWARE_SPECS.md.

## [1.9.4-Alpha+202603142150] - 2026-03-14

### ADDED
- **Asynchronous Integrity Auditing:** Implemented a robust background job system (`IntegrityCheckJob`) for performing full-scale cryptographic audits without blocking the UI.
- **Real-Time Audit Progress:** Integrated a dynamic progress modal with estimated time remaining and live status updates for system integrity verification.
- **Performance Analytics V2:** Re-engineered the dashboard and statistics controllers to utilize pre-aggregated `DailyDepartmentMetric` data, ensuring sub-second response times for 1M+ record datasets.
- **Metrics Backfilling Utility:** Added `dts:backfill-metrics` Artisan command to generate historical analytics data for existing or seeded datasets.
- **Consolidated Documentation Suite:** Deeply refactored the project's documentation into three high-depth technical guides (`ARCHITECTURE.md`, `HARDWARE_SPECS.md`, `USER_GUIDE.md`), eliminating redundancy and improving navigation.
- **Windows-Specific Performance Tuning:** Added instructions for managing MySQL RAM allocation and CPU Processor Affinity (`start /affinity`) on Windows environments.

### FIXED
- **Analytics "RAM Trap" Finalization:** Completely eliminated model hydration in dashboard queries by transitioning all chart data to indexed metric tables and SQL-level aggregations.
- **Quick-Start Redundancy:** Streamlined the `README.md` setup guide by consolidating multiple install steps into the automated `composer run setup` command.
- **Audit UI Stability:** Resolved several UI flickering and modal stacking issues in the System Health and Statistics dashboards.

### CHANGED
- **Removed Test Purpose:** Excised the "System Test: Full Route" purpose from seeders and UI dropdowns to ensure only valid official purposes are utilized.
- **Schema Optimization:** Enhanced the "Genesis" migration with composite indexes on `current_department_id` and status fields for optimized document movement tracking.
- **Documentation Categorization:** Organized the Project Commands Matrix into logical functional groups (Setup, Development, Production, etc.) for better developer onboarding.

## [1.9.3-Alpha+202603122031] - 2026-03-12

### ADDED
- **Comprehensive Documentation Overhaul:** Expanded the core technical documentation suite to provide more technical depth while maintaining accessibility for non-technical stakeholders.
    - **Dedicated Glossaries:** Integrated specialized glossaries into the Table of Contents of all primary `.md` files to define complex terms (e.g., Ed25519, HMR, Buffer Pool, PQC) within their local context.
    - **`ADMINISTRATION.md` Expansion:** Detailed the "RAM Trap" avoidance strategy using MySQL 8.0 Window Functions and expanded the "1 Million Document Strategy" for high-performance indexing.
    - **`ARCHITECTURE.md` Expansion:** Simplified the RBAC and "Trust Builder" models through intuitive analogies ("Traffic Cop," "Digital Seal") and clarified the "Active Guard" two-layer audit logic.
    - **`HARDWARE_SPECS.md` Expansion:** Introduced the "Highway" analogy for CPU thread allocation and the "Desk vs. Filing Cabinet" analogy for memory optimization (InnoDB Buffer Pool).
    - **`OPERATIONS.md` Expansion:** Detailed the "5-Pillar" multi-threaded development architecture and the "Nomadic HTTPS" setup for secure, cross-device mobile testing.
    - **`QUANTUM_SAFETY.md` Expansion:** Simplified the threats posed by Shor's and Grover's algorithms through "Lock Picker" and "Library Searcher" analogies and detailed the roadmap for Hybrid/Lattice-Based cryptography.

## [1.9.2-Alpha+202603092200] - 2026-03-09

### ADDED
- **High-Scale Performance Engine:** Re-architected the analytics layer to handle 1,000,000+ documents and 10,000,000+ logs without worker saturation.
- **Aggressive Analytics Caching:** Implemented a multi-tier caching strategy for the Admin Dashboard and System Health monitor, eliminating the "deadlock" behavior during heavy API fetching.
- **Hash Integrity Debugger:** Introduced a new administrative diagnostic tool that allows real-time inspection of cryptographic hash components and recalculated values directly from the UI.
- **Collision-Resistant Hashing:** Hardened the "Trust Builder" formula with delimited field concatenation (`|`), preventing mathematical collisions during chain verification.
- **Mock-Signature Awareness:** Enhanced the integrity checker to mathematically validate `MOCK_SIG` format signatures for seeded datasets while maintaining strict Ed25519 enforcement for production logs.

### FIXED
- **Worker Saturation Deadlock:** Resolved a critical system hang where concurrent dashboard API requests blocked navigation to other administrative pages.
- **The "RAM Trap" (SQL Refactoring):** Migrated heavy model-iteration logic to MySQL 8.0 Window Functions and SQL-level aggregations, reducing PHP memory usage by 95% at scale.
- **Integrity Validation False-Positives:** Corrected mismatches between seeder-generated logs and strict cryptographic verification logic.
- **Implicit Precision Warnings:** Resolved PHP log spam caused by float-to-int conversions in Blade templates during time-series calculations.
- **Missing Variable Bug:** Patched a 500 error in the district submission endpoint caused by a missing `$` prefix.

### CHANGED
- **Performance-Critical Indexing:** Consolidated composite indexes for document status and date tracking into the "Genesis" migration for optimized production delivery.
- **Seeder Reversion:** Reverted default document creation to 10,000 for standard development cycles while preserving high-scale architectural optimizations.

## [1.9.1-Alpha+202603082130] - 2026-03-08

### ADDED
- **Historical Signature Archiving:** Implemented a robust key versioning system that preserves historical digital signatures after a PIN reset. Old public keys are now archived in the `user_public_key_histories` table, allowing the system to mathematically verify ancient logs even if the user has since changed their keys.
- **Atomic Integrity Locking:** Upgraded the workflow controllers (`DocumentController`, `TaskController`, `ReleasingController`) to explicitly bind cryptographic signatures to the *finalized* document state. State hashes are now calculated post-update and passed directly to the ledger, ensuring 100% verification accuracy.

### FIXED
- **Timestamp Rounding Race Condition:** Resolved a critical integrity mismatch where microseconds in `Carbon::now()` caused discrepancies between the hashed timestamp and the database-stored value. Standardized on second-level precision (`startOfSecond()`) across all cryptographic operations.
- **Stale Tracking Cache:** Fixed a bug where newly submitted documents appeared missing due to 55-second route caching. Moved the tracking portal to a non-cached route for real-time guest feedback.
- **Guest Portal UI Alignment:** Standardized the theme switcher positioning and forced vertical scrollbar visibility across the guest submission and tracking pages, ensuring visual consistency with the authenticated dashboard.
- **Deterministic State Hashing:** Hardened the `calculateStateHash` formula with explicit type casting and null-safe JSON normalization to prevent false-positive tampering alerts.

### CHANGED
- **Schema Consolidation:** Refactored the database architecture by squashing incremental migrations for Ed25519 keys, prediction metadata, and key history into the initial schema definition for cleaner environment initialization.

## [1.9.0-Alpha+202603081100] - 2026-03-08

### ADDED
- **Nomadic HTTPS Infrastructure (mDNS):** Implemented a robust Local Area Network (LAN) development environment utilizing mDNS (`.local`) hostnames. This allows cross-device access (iPhone/Laptop) without needing to update IP addresses when switching between hotspots and Wi-Fi.
- **Secure Development Proxy:** Integrated `local-ssl-proxy` into the project's ecosystem via a new `composer proxy` command. This bridges the internal PHP server (HTTP) to a secure HTTPS port, unlocking mobile camera APIs for the QR scanner.
- **Protocol Symmetry Enforcement:** Added `URL::forceScheme('https')` logic to the `AppServiceProvider` for development environments, ensuring all generated links, forms, and redirects correctly utilize the secure proxy scheme.
- **Vite Environment Synchronization:** Refactored `vite.config.js` to explicitly load the `.env` file and inject `APP_URL` into the Node process, resolving the "Broken UI" and HMR asset loading issues on external devices.

### FIXED
- **Protocol Mismatch Errors:** Resolved `PR_END_OF_FILE_ERROR`, binary junk character responses during login, and HTTP redirects during logout by implementing `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`. This ensures Laravel correctly detects the secure proxy layer.
- **Nomadic Setup Documentation:** Updated `GEMINI.md` with precise `mkcert` instructions for generating "Universal" certificates that cover localhost, multiple IPs, and mDNS hostnames.

## [1.8.11-Alpha+202603081000] - 2026-03-08

### ADDED
- **Interactive Theme Switcher:** Implemented a global, Alpine.js-powered theme switcher component (`<x-theme-switcher />`).
    - Integrated into the desktop and mobile navigation bars for all authenticated users.
    - Added to the Guest Portal (Welcome, Track, Success) and Auth pages (Login, Register) in a fixed top-right position.
    - Seamlessly toggles between Tailwind CSS `dark` mode and Bootstrap 5 `data-bs-theme="dark"`.
    - Persists user preference via `localStorage`, ensuring the selected theme remains active across sessions.
- **Cross-Browser Theme Consistency System:** Implemented a robust theme-detection script in the `<head>` of all layouts (App, Guest, and standalone portal pages). 
    - Automatically detects system/browser dark mode preferences via `prefers-color-scheme`.
    - Ensures the entire project, including the Guest Portal, correctly renders in dark mode when requested by the browser.
- **Bootstrap 5 Dark Mode Integration:** Updated the public-facing portal (Welcome, Track, Success pages) to utilize Bootstrap 5's `data-bs-theme="dark"` attribute, resolving the "white background" issue in dark-preferring browsers.
- **Enabled Guest-Portal Interaction:** Integrated Alpine.js into the public-facing JavaScript bundle (`bootstrap_public.js`), enabling the theme switcher and other interactive components to function correctly on non-authenticated pages.

### FIXED
- **User Management Accessibility:** Improved visibility of the "Edit User" link in the administrative table by adding `dark:text-indigo-400` classes, ensuring it is clearly legible against dark backgrounds.

## [1.8.10-Alpha+202603080830] - 2026-03-08

### FIXED
- **Trust Builder Hashing Precision:** Standardized cryptographic hashing to second-level precision (stripping microseconds) across the `DocumentLog` model, `VerifyIntegrityChain` command, and `RebuildHashChain` command. This eliminates false-positive integrity failures caused by database timestamp rounding.
- **Seeder Timestamp Corruption:** Resolved a critical object reference bug in `DocumentSeeder` where all logs for a single document shared the same final timestamp. This fix restores the accuracy of the historical ledger and fixes the "Zero TAT" issue in dashboard charts.
- **Massive Record Query Exception:** Resolved `QueryException: Prepared statement contains too many placeholders` in the System Health Monitor. Implemented manual pagination and chunked `whereIn` queries to handle integrity reports with 90,000+ records safely.
- **Tailwind 4 Modal Backdrops:** Standardized backdrop syntax and stacking contexts for all system modals (Report Progress, Signing, Decline, and Chart modals), resolving the "white background" overlay issue introduced during the CSS synchronization.

### ADDED
- **Intelligent Simulation Capping:** Updated `DocumentSeeder` to automatically cap data generation at the most recent completed work week (Friday at 5:00 PM).
- **Improved Weekend Data Handling:** Refined seeder logic to move weekend-generated dates back to the preceding Friday instead of forward to Monday, preventing the creation of "future-dated" records relative to the current day.

### CHANGED
- **User Management UX Optimization:** 
    - Moved the "Edit User" link directly under the Name column and removed the redundant "Actions" column to maximize horizontal space.
    - Relocated the "Delete User" functionality into the Edit view for a safer, more centralized workflow.
    - Enabled `break-all` wrapping for the email column and standardized vertical alignment for clipboard copy buttons.

## [1.8.9-Alpha+202603080708] - 2026-03-08

### ADDED
- **Digital Signature Reset Feature:** Implemented an administrative tool to reset a user's digital signature (Ed25519 keys and PIN association). This allows users who have forgotten their Security PIN to re-initialize their cryptographic identity.
    - Added `resetSignature` method to `UserManagementController`.
    - Integrated "Reset Digital Signature" buttons in both the User Edit page and the main User Management list.
    - Added a global signature status indicator (Active vs. Not Set) in the administrative user table.
- **Enhanced Mass Assignment Protection:** Updated the `User` model to include `security_key_set_at` in the `$fillable` array, ensuring consistent state management during signature resets and initializations.

### FIXED
- **DocumentSeeder Performance & Stability:** Completely refactored Stage 2 of the `DocumentSeeder` to utilize database chunking and batch insertions.
    - Resolved a critical `QueryException` where the `documents` table would become temporarily inaccessible or unresponsive during massive sequential updates.
    - Improved seeding speed and memory efficiency by grouping `DocumentLog` and `database_metrics` insertions into chunks of 200 documents.

## [1.8.8-Release+202603072130] - 2026-03-07

### ADDED
- **Production-Ready Seeder:** Scaled `DocumentSeeder` to support 10,000+ realistic multi-step document transactions.

## [0.3.0] - 2025-12-29 (Public Tracking)

### Added
- **Public Tracking Portal:** New `/track` route with interactive subway map status display.

## [0.2.0] - 2025-12-29 (Workflow & Responsiveness)

### Added
- **Core Document Routing & Task Management:** Task completion logic and responsive table layouts.

## [0.1.0] - 2025-12-26 (Initial Prototype)

### Added
- **Initial Project Setup:** Framework initialization, database schema, RBAC middleware, and initial hash-chain audit log implementation.
