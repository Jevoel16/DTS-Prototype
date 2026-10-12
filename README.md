# DepEd Iligan - Document Tracking System (DTS) Prototype

A modern, production-ready web application for digitizing, tracking, and managing official documents for the **DepEd Division of Iligan City**. It features role-based workflow routing, real-time analytics, physical QR code tracking, and a cryptographically secure audit trail powered by SHA-256 hash-chaining and Ed25519 digital signatures.

---

## ⚡ Architectural Evolution: The Shift to Pure Vanilla PHP

The system was originally built on Laravel 12 and later ported to pure vanilla PHP. The Laravel codebase has been retired (it remains in git history); its research paper and documentation were carried over into this repository:

```
DTS Prototype/                            <-- ACTIVE CODEBASE (Pure Vanilla PHP 8.3)
├── src/ public/ scripts/ ...             <-- Application code
├── paper/                                <-- Research manuscript & chapters
├── documentation/laravel-era/            <-- Original Laravel-era docs & screenshots
└── from_dts/                             <-- Legacy DTS data export (used by scripts/seeders/import-legacy.php)
```

### Why Port to Pure Vanilla PHP 8.3?

1. **Framework-Free Performance:** Eliminates framework bootstrapping overhead, delivering near-instant page loads and sub-millisecond raw PDO database query execution.
2. **Zero-Dependency Core:** Runs natively using pure PHP 8.3 and MySQL 8.0 without heavy framework runtime dependencies.
3. **Pessimistic Locking & Transaction Safety:** Centralizes database state mutations inside [DocumentWorkflowService.php](src/Services/DocumentWorkflowService.php) using explicit `PDO` transactions and `SELECT ... FOR UPDATE` row-level locking to eliminate race conditions under concurrent loads.
4. **Container-Native Scheduling:** Replaced host `cron` daemons with a unified CLI worker ([console.php](console.php)) featuring an internal task scheduler loop for telemetry sampling, TAT backfilling, metrics rollups, response cache GC, and automated stale pending document garbage collection inside containerized environments (Distrobox / Docker).

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

## 📄 Example Outputs & Generated Reports

Real examples of document tracking forms and administrative export reports are located in [documentation/exports](documentation/exports):

- **Sample Tracking Form (PDF):** [document-tracking-form-DEPED-E5345FDA4F.pdf](documentation/exports/document-tracking-form-DEPED-E5345FDA4F.pdf) — Generated PDF containing submitter information, document requirements, and a scannable QR code.
- **Sample Release Report (XLSX):** [released-documents-20260728_000645-677docs.xlsx](documentation/exports/released-documents-20260728_000645-677docs.xlsx) — Streaming Excel export of completed document throughput generated via `OpenSpout`.

---

## 📖 System Documentation & Manuals

Comprehensive user manuals are provided in [documentation/user_manuals](documentation/user_manuals):

- 📘 **[Administrator Manual](documentation/user_manuals/admin_manual.md):** Account management, database performance, integrity checks, backups, and snapshot Auto-Resolve.
- 📗 **[Records Officer Manual](documentation/user_manuals/officer_manual.md):** Document intake, route finalization, QR tracking form printing, camera scanning, and releasing.
- 📙 **[Department Staff Manual](documentation/user_manuals/staff_manual.md):** Departmental task processing, physical folder receipt, action remarks, and digital signatures.
- 🔐 **[Cryptographic Logs Explained](documentation/CRYPTOGRAPHIC_LOGS_EXPLAINED.md):** How hash-chain logs work (mechanics), why seals/signatures match administrative chain-of-custody needs, and cryptography vs encryption.
- 📖 **[Cryptographic Hash Verification Layman Guide](documentation/HASH_VERIFICATION_LAYMAN_GUIDE.md):** Simple, step-by-step breakdown of how document hashes, payload state hashes, digital signatures, and tamper detection work without complex jargon.
- 🎯 **[Value Proposition & Multi-Stakeholder Pitch](documentation/PITCH_AND_VALUE_PROPOSITION.md):** Architectural value propositions and technical pitches for IT Admins, Executives, Cryptographers, Office Staff, and Citizens.

---

## ⚡ Quick Start

1. **Clone the repository and open its root folder.**

2. **Install Dependencies & Build Assets:**
   ```bash
   npm install
   npm run build
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   # Edit .env to set DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   ```

4. **Initialize & Seed Production Database:**
   ```bash
   composer run seed:prod
   ```

5. **Launch Local Server & Background Worker:**
   ```bash
   # Starts the Web Server, PostCSS Watcher, and Async Queue Worker seamlessly
   composer run dev
   ```

---

## 🌱 Database Seeding Commands (`scripts/seeders`)

- **Production Setup (`composer run seed:prod` / `node scripts/seeders/setup-production-db.js`):** Builds database tables from `database.sql` and seeds 14 DepEd departments, baseline document purposes, and initial user accounts.
- **High-Speed Direct Database Seeder (`composer run seed:fast [docs]` / `php scripts/seeders/fast-seed.php`):** Generates up to 50,000+ document logs directly in MySQL in seconds by pre-decrypting Ed25519 keys into RAM.
- **Slow-Speed API Seeder (`composer run seed:dev [docs] [chunk] [concurrency]` / `node scripts/seeders/seed.js`):** Dispatches live HTTP requests against the web server to simulate multi-user traffic and session handling, also functions as a concurrency test.

---

## 🛠 Script Directory Guide (`scripts`)

| Command / Script | Purpose |
| :--- | :--- |
| `composer run worker` | Runs [console.php](console.php) background job processor and telemetry scheduler. |
| `composer run dev` | Runs [scripts/dev.php](scripts/dev.php) to start local dev server & Tailwind watcher. |
| `composer run corrupt` | Runs [scripts/corrupt.php](scripts/corrupt.php) to simulate database tampering and test JIT Active Guard auto-freeze. |
| `php scripts/tune-database.php` | Analyzes system memory and outputs optimal MySQL `my.cnf` buffer pool configurations. |
| `php scripts/generate-keys.php` | CLI tool for generating missing Ed25519 digital signature keypairs. |
| `php scripts/concurrency_test.php` | Evaluates pessimistic locking performance under high-concurrency transaction loads. |

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
