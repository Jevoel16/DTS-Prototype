# DepEd Iligan - Document Tracking System (DTS) Prototype

A web application for digitizing, tracking, and managing official documents for the **DepEd Division of Iligan City**. It features role-based workflow routing, analytics dashboards, physical QR code tracking, and a cryptographically secure audit trail powered by SHA-256 hash-chaining and Ed25519 digital signatures.

---

## ⚡ Architectural Evolution: From Laravel to Plain PHP

The system was originally built on Laravel 12 and later ported to plain PHP (no framework). The Laravel codebase has been retired (it remains in git history, see commit `2b314a1`); its research paper and documentation were carried over into this repository:

```
DTS-Prototype/                            <-- ACTIVE CODEBASE (plain PHP 8.3 + MySQL 8)
├── public/                               <-- Web root: index.php front controller, CSS/JS/images
├── src/                                  <-- Controllers, Core (Router, Database, IntegrityManager), Services, Jobs, Views
├── console.php / runner.php              <-- Queue worker + scheduler / single-job runner
├── nginx/site.conf                       <-- NGINX template used on Windows and Linux
├── scripts/                              <-- dev/prod launchers, migrations, metrics jobs, seeders
├── database.sql                          <-- Full schema
├── documentation/                        <-- Manuals and technical docs (documentation/laravel-era = historical)
├── paper/                                <-- Research manuscript & chapters
└── from_dts/                             <-- Legacy DTS data export (see "Legacy / simulated data")
```

### Why plain PHP?

1. **No framework overhead:** Requests go straight from [public/index.php](public/index.php) to a small regex [Router](src/Core/Router.php) and raw PDO queries.
2. **Small dependency footprint:** Only four Composer packages — `dompdf` (tracking-form PDFs), `chillerlan/php-qrcode` (QR codes), `openspout` (XLSX reports) and their sub-dependencies. The front end uses Tailwind (build-time only) and vendored Chart.js / html5-qrcode.
3. **Optimistic locking & transactions:** Document state changes are centralized in [DocumentWorkflowService.php](src/Services/DocumentWorkflowService.php). Each update runs inside a PDO transaction and only succeeds if the row's `version` still matches (`WHERE version = :version`), so two people acting on the same document at once cannot both win.
4. **Built-in scheduler:** One CLI worker ([console.php](console.php)) processes queued jobs (reports, backups, integrity checks) and runs periodic tasks: DB telemetry sampling (5 min), department metrics backfill (30 min), cache garbage collection and log rotation (hourly), metrics rollup and stale-pending-document expiry (daily). No cron needed.

---

## 📸 System Screenshots

### Guest Submission
<img src="documentation/screenshots/Guest%20Submission%20(Filled).png" width="100%" alt="Guest Submission" style="border: 1px solid black;">

### Guest Submission Success
<img src="documentation/screenshots/Guest%20Submission%20Success.png" width="100%" alt="Guest Submission Success" style="border: 1px solid black;">

### Document Intake
<img src="documentation/screenshots/Document%20Intake.png" width="100%" alt="Document Intake" style="border: 1px solid black;">

### Department Tasks
<img src="documentation/screenshots/Department%20Tasks.png" width="100%" alt="Department Tasks" style="border: 1px solid black;">

### Department Digital Signature
<img src="documentation/screenshots/Department%20Digital%20Signature.png" width="100%" alt="Department Digital Signature" style="border: 1px solid black;">

### Document Releasing
<img src="documentation/screenshots/Document%20Releasing.png" width="100%" alt="Document Releasing" style="border: 1px solid black;">

### Admin Dashboard
<img src="documentation/screenshots/Admin%20Dashboard.png" width="100%" alt="Admin Dashboard" style="border: 1px solid black;">

### Admin Dashboard (Dark Mode)
<img src="documentation/screenshots/Admin%20Dashboard%20(Dark%20Mode).png" width="100%" alt="Admin Dashboard (Dark Mode)" style="border: 1px solid black;">

### Admin System Overview
<img src="documentation/screenshots/Admin%20System%20Overview.png" width="100%" alt="Admin System Overview" style="border: 1px solid black;">

### [View More Screenshots ➔](documentation/screenshots/)

---

## ✨ Features

- **Guest submission & tracking:** Citizens submit documents without an account, receive a tracking code (`DEPED-` + 10 hex characters) and a printable PDF tracking form with a QR code, and can track several codes at once.
- **Purpose-based routing:** Each purpose has a suggested department route that the Records Officer can adjust before finalizing; "Others" purposes are mapped by the officer.
- **Department workflow:** Statuses `pending → in_transit → processing → ready_for_release → completed`, plus `declined` and `frozen`. Staff receive, process and forward documents; officers release them with their PIN.
- **Cryptographic audit trail:** Every action is a hash-chained log entry signed with the user's Ed25519 key (private key encrypted with their PIN). See [Cryptographic Logs Explained](documentation/CRYPTOGRAPHIC_LOGS_EXPLAINED.md).
- **Integrity monitoring:** Admins run a background verification of every chain, can **Rebuild Chain** for a corrupted entry, and can **Freeze All** documents in an emergency.
- **Reports:** Officers and staff queue XLSX reports of released documents; finished reports stay in **Past Reports** for re-download.
- **Notifications:** In-app bell for report completion and workflow events (polled every 30 s).
- **Dashboards:** Admin charts (load, throughput, decline trends, hotspots, peak hours) and DB performance telemetry; department statistics for staff/officers.
- **Backups:** One-click `mysqldump` backups (zipped) with download, restore and delete.
- **Automatic expiry:** Pending submissions not taken in by the Records Office within 3 days are declined automatically with a signed system log entry.
- **Fast pages:** Cursor pagination, a 55-second per-user HTML response cache, and PJAX navigation. See [Caching & PJAX Explained](documentation/CACHING_AND_PJAX_EXPLAINED.md).

---

## 📄 Example Outputs & Generated Reports

- **Sample Tracking Form (PDF):** [document-tracking-form-DEPED-E5345FDA4F.pdf](documentation/exports/document-tracking-form-DEPED-E5345FDA4F.pdf) — submitter information, document requirements, and a scannable QR code.
- **Release Report (XLSX):** generated from **Statistics → Generate Report** and streamed by `OpenSpout` (`released-documents-<timestamp>-<count>docs[-filters].xlsx`). Generated `.xlsx` files are git-ignored, so no sample is committed.

---

## 📖 System Documentation & Manuals

- 📘 **[Administrator Manual](documentation/user_manuals/admin_manual.md):** Accounts, dashboards, integrity checks, Freeze All / Rebuild Chain, backups.
- 📗 **[Records Officer Manual](documentation/user_manuals/officer_manual.md):** Intake, route finalization, tracking forms, QR scanning, releasing, reports.
- 📙 **[Department Staff Manual](documentation/user_manuals/staff_manual.md):** Receiving, processing, remarks, and digital signatures.
- 🏗️ **[Architecture](documentation/ARCHITECTURE.md):** Request flow, document lifecycle, hash chaining, routing, worker & scheduler.
- 🖥️ **[Hardware Specs & MySQL Tuning](documentation/HARDWARE_SPECS.md):** Server sizing and InnoDB buffer pool tuning.
- 🔐 **[Cryptographic Logs Explained](documentation/CRYPTOGRAPHIC_LOGS_EXPLAINED.md)** and the **[Layman Guide](documentation/HASH_VERIFICATION_LAYMAN_GUIDE.md)** to hash verification.
- 🛡️ **[Quantum Safety](documentation/QUANTUM_SAFETY.md):** How the hash chain and signatures hold up against quantum attacks.
- ⚡ **[Caching & PJAX Explained](documentation/CACHING_AND_PJAX_EXPLAINED.md)**
- 🎯 **[Value Proposition & Pitch](documentation/PITCH_AND_VALUE_PROPOSITION.md)**
- 🗄️ **[Laravel-era docs](documentation/laravel-era/):** Historical only; they describe the retired Laravel version.

---

## ⚡ Quick Start

### Prerequisites

- **PHP 8.3+** with the `pdo_mysql`, `sodium`, `mbstring`, `gd`, `zip` and `openssl` extensions (Windows also needs `php-cgi.exe`, which ships with PHP).
- **MySQL 8**, with the `mysql` and `mysqldump` clients on `PATH` (used by backups).
- **Composer** and **Node.js** (Tailwind build and the Node seeders).
- **NGINX** (recommended; see below). Without it, Windows falls back to PHP's built-in server.

### Steps

1. **Install dependencies & build the CSS**
   ```bash
   composer install
   npm ci
   npm run build
   ```
2. **Configure the environment**: copy `.env.example` to `.env` and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. **Create the database and seed the base data** (creates `DB_DATABASE` if needed, loads [database.sql](database.sql), seeds the 14 departments, purposes and accounts):
   ```bash
   composer run seed:prod
   ```
4. **Start the server and worker**
   ```bash
   composer run dev
   ```
   Open **https://localhost:8000** (NGINX; accept the self-signed certificate warning) or **http://localhost:8000** (built-in server fallback). Each user sets a signing PIN (at least 6 characters) the first time they log in.

**Upgrading an existing database** created before October 2026: run `composer run migrate` once. It adds the new indexes and drops the unused Laravel tables, and is safe to run again.

---

## 🌐 Running with NGINX (Windows and Linux)

The same template, [nginx/site.conf](nginx/site.conf), is used on both systems. `composer run dev` and `composer run prod` fill in the machine-specific values (project path, LAN IP, PHP connection, certificate and log paths), generate a self-signed certificate for `localhost` and your LAN IP, and serve **HTTPS on port 8000**. The config also enables gzip, 30-day browser caching for `/css`, `/js`, `/fonts`, `/images`, and a 300 s FastCGI timeout for reports and PDFs.

| | Windows | Linux (Debian/Ubuntu) |
| :--- | :--- | :--- |
| NGINX | [nginx for Windows](https://nginx.org/en/download.html) zip, unzipped to `C:\nginx` (or set `NGINX_PATH` in `.env`) | `sudo apt install nginx` |
| PHP connection | 4 `php-cgi.exe` processes on `127.0.0.1:9123-9126`, started by the script | PHP-FPM socket `/run/php/phpX.Y-fpm.sock` (`sudo apt install phpX.Y-fpm`) |
| Generated config | `storage/nginx/conf/nginx.conf` (project-local, no admin rights) | `/etc/nginx/sites-available/dts-prototype` (uses `sudo`) |
| Certificate | `storage/ssl/` (via `openssl`, e.g. the one bundled with Git for Windows) | `/etc/ssl/certs` + `/etc/ssl/private` (needs the `ssl-cert` group) |
| Logs | `storage/logs/dts_*.log`, `storage/nginx/logs/` | `/var/log/nginx/dts_*.log` |
| Stop | Ctrl+C stops NGINX, php-cgi and the worker | Ctrl+C stops NGINX and PHP-FPM |

A project set up on Windows runs unchanged on Linux and the other way round: generated files live in git-ignored folders and are rebuilt on each start.

**Fallback (Windows, no NGINX):** `composer run dev` runs `php -S 0.0.0.0:8000 -t public public/router.php` (plain HTTP) plus the worker. [public/router.php](public/router.php) sends every non-file URL to the front controller, the way NGINX's `try_files` does, so URLs ending in a filename (such as backup downloads) work. `composer run prod` requires NGINX.

---

## 🛠 Commands

| Command | Purpose |
| :--- | :--- |
| `composer run dev` | Clears the response cache, starts NGINX (or the fallback) and the queue worker. The worker is restarted automatically after its 100-job / 256 MB limit on Windows. |
| `composer run prod` | Same server setup for production, plus the worker (`-- --no-worker` if systemd/supervisor runs it). |
| `composer run worker` | Runs only the [console.php](console.php) queue worker and scheduler. |
| `npm run dev` / `composer run css` | Tailwind watcher while editing views / one-off minified build (`public/css/tailwind.css`). |
| `composer run migrate` | Upgrades an existing database to the current schema ([scripts/migrate.php](scripts/migrate.php)). |
| `composer run proxy` | Exposes `https://localhost:8000` through a Cloudflare quick tunnel (`cloudflared` must be installed). |
| `composer run corrupt` | [scripts/corrupt.php](scripts/corrupt.php) tampers with a document to demonstrate tamper detection and auto-freeze. |
| `php scripts/tune-database.php` | Sets the running MySQL's InnoDB buffer pool to 4 GB (`SET GLOBAL`, lost on restart; see [Hardware Specs](documentation/HARDWARE_SPECS.md) for the permanent `my.cnf` setting). |
| `php scripts/generate-keys.php` | Generates missing Ed25519 keypairs for users. |
| `php scripts/concurrency_test.php` | Simulates concurrent updates to the same document to exercise the optimistic locking. |
| `php scripts/daily-backup.php` | Creates a backup from the command line (for an OS scheduler, if wanted). |
| `php scripts/backfill-metrics.php [--full]` | Rebuilds department metrics for the last 2 days (or all history). Run automatically by the worker. |

Environment variables (`DB_*`, `APP_DEBUG`) set in the shell override `.env`, e.g. to point a one-off command at another database.

---

## 🌱 Database Seeding (`scripts/seeders`)

- **`composer run seed:prod`** ([setup-production-db.js](scripts/seeders/setup-production-db.js)): **drops and recreates all tables**, then seeds departments, purposes and the accounts below.
- **`composer run seed:fast [docs]`** ([fast-seed.php](scripts/seeders/fast-seed.php)): clears documents/logs and inserts `docs` (default 10,000) simulated documents with valid signed hash chains directly in MySQL.
- **`composer run seed:dev [docs] [chunk] [concurrency]`** ([seed.js](scripts/seeders/seed.js)): drives the running web server over HTTP to simulate many users; doubles as a concurrency test. Calls `https://localhost:8000` by default; set `APP_URL=http://localhost:8000` in `.env` when using the fallback server.
- **`composer run seed:legacy [-- file.csv]`** ([import-legacy.php](scripts/seeders/import-legacy.php)): see below.

### Legacy / simulated data (`from_dts/`)

`from_dts/` holds an export of the previous DTS's routing records (`dts_docroutes`).

- `week_2026_07_20_to_07_23.csv` and `week_2026_07_27_to_07_30.csv` (tracked in git): weekly slices produced by [split_weekly_data.py](from_dts/split_weekly_data.py).
- `dts_docroutes (1).csv` (~48 MB) and `dts_docroutes.sql` (~52 MB): the full export. **These stay on your disk but are no longer tracked in git** (too large). Copy them over manually if you clone the repo elsewhere.

To use the data:

- **Import a week into the DTS** (replaces existing documents and logs, then rebuilds metrics):
  ```bash
  composer run seed:legacy
  ```
  Pass another file with `composer run seed:legacy -- from_dts/week_2026_07_20_to_07_23.csv`.
- **Regenerate the weekly CSVs** from the full export: `python from_dts/split_weekly_data.py`.
- **Inspect the raw export in MySQL:** load `from_dts/dts_docroutes.sql` into a separate scratch database (it creates its own `dts_docroutes` table and does not touch the DTS tables).

---

## 🔐 Default Test Accounts (Password: `password`)

| Role | Username |
|:---|:---|
| **Administrator** | `admin` |
| **Records Officer** | `records.unit` |
| **Staff: Cash Unit** | `cash.unit` |
| **Staff: Admin Unit** | `administrative.unit` |
| **Staff: Personnel** | `personnel.unit` |
| **Staff: Supply Unit** | `supply.unit` |
| **Staff: Budget Unit** | `budget.unit` |
| **Staff: Accounting** | `accounting.unit` |
| **Staff: Legal Unit** | `legal.unit` |
| **Staff: Health & Nutrition** | `health.and.nutrition` |
| **Staff: BAC Unit** | `bids.and.awards.committee.unit` |
| **Staff: SDS Office** | `schools.division.superintendent.office` |
| **Staff: ASDS Office** | `assistant.schools.division.superintendent.office` |
| **Staff: CID** | `curriculum.implementation.division` |
| **Staff: SGOD** | `school.governance.and.operations.division` |

---

## ⚖️ License & Usage

**Non-Commercial Government-Backed Project License**

This project is a government-backed initiative for the **DepEd Division of Iligan City**. 

You are free to clone, use, and modify the system to make it your own for internal use. However, **you may not sell, rent, profit from, or redistribute this system or its derivatives in any way.** 

Please see the [LICENSE](LICENSE) file for more details.

---

Developed for the **DepEd Division of Iligan City**.
