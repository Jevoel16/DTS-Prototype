# DTS System Architecture (PHP)

## Summary
How the plain-PHP Document Tracking System for the **DepEd Division of Iligan City** is put together: the stack, how a request is handled, the document lifecycle, the cryptographic ledger, route suggestions, and the background worker. Written for developers and technical reviewers. The retired Laravel design is in [laravel-era/ARCHITECTURE.md](laravel-era/ARCHITECTURE.md) for history only.

## Table of Contents
1. [Technology Stack](#1-technology-stack)
2. [Request Flow](#2-request-flow)
3. [Document Lifecycle](#3-document-lifecycle)
4. [The Cryptographic Ledger](#4-the-cryptographic-ledger)
5. [Route Suggestions](#5-route-suggestions)
6. [Background Worker & Scheduler](#6-background-worker--scheduler)
7. [Data & Performance Notes](#7-data--performance-notes)
8. [Glossary](#8-glossary)

---

## 1. Technology Stack

| Layer | Technology |
|:---|:---|
| Web server | **NGINX** (HTTPS on port 8000) with PHP-FPM on Linux or a `php-cgi` pool on Windows; PHP built-in server as a Windows dev fallback. See the README's "Running with NGINX". |
| Backend | **PHP 8.3+**, no framework: own Router, PDO wrapper, middleware and views in `src/`. |
| Database | **MySQL 8** (JSON columns, a generated `action_category` column, composite indexes). |
| Crypto | PHP **sodium**: Ed25519 signatures, Argon2id PIN key derivation; SHA-256 hashing; AES-256-CBC key wrapping. |
| PHP libraries | `dompdf` (tracking-form PDF), `chillerlan/php-qrcode` (QR codes), `openspout` (streamed XLSX reports). |
| Front end | **Tailwind CSS 3** (built to `public/css/tailwind.css`), vendored **Chart.js**, **html5-qrcode** (camera scanning), **SortableJS** (route drag-and-drop), and a small PJAX router for page swaps. |
| Node.js | Build-time only: Tailwind and the seeders. |

---

## 2. Request Flow

```
Browser ──HTTPS──> NGINX ──static file? yes──> served from public/ (gzip, 30-day cache)
                      │ no
                      ▼
               public/index.php  (session, CSRF token, timezone Asia/Manila, APP_DEBUG)
                      ▼
               src/Core/Router.php  (regex routes, CSRF check on POST, 404)
                      ▼
               Middleware: AuthMiddleware → RoleMiddleware:<roles> → CacheMiddleware:<ttl>
                      ▼
               Controller (src/Controllers) → Services / Policies / Core
                      ▼
               View (src/Views, plain PHP templates; layouts/app.php wraps pages)
```

- **AuthMiddleware** requires a login and blocks POST actions until the user has set up a signing key (the setup is a modal in `layouts/app.php`, posted to `/security-key`).
- **CacheMiddleware** stores each user's rendered page in `cache/responses/` for 55 s and re-injects the current CSRF token on cache hits. See [CACHING_AND_PJAX_EXPLAINED.md](CACHING_AND_PJAX_EXPLAINED.md).
- **Database** (`src/Core/Database.php`) is one PDO connection per process with real prepared statements; long-running processes call `ensureConnected()` to survive MySQL's idle timeout.
- Configuration comes from `.env` (`src/Config/config.php`); real environment variables override it.

---

## 3. Document Lifecycle

All state changes go through [DocumentWorkflowService](../src/Services/DocumentWorkflowService.php). Each method runs in a PDO transaction, asks [DocumentPolicy](../src/Policies/DocumentPolicy.php) whether the user may act, which includes the integrity check, then updates the row with an **optimistic lock** (`... WHERE id = :id AND version = :version`, `version = version + 1`). If another user changed the row first, the update affects 0 rows and the user is asked to refresh.

| Step | Status after | Method | Log action |
|:---|:---|:---|:---|
| Guest submits | `pending` | `submitDocument` (via `GuestController@store`) | `Submitted` |
| Officer finalizes route | `in_transit` | `finalizeIntake` | `Accepted and Document Routing finalized` |
| Department scans folder | `processing` | `scanDocument` | `Received` |
| Department completes step | `in_transit` (next dept) | `completeTask` | `Processing Complete` |
| Records receives after last step | `ready_for_release` | `scanDocument` | `Ready for Releasing` |
| Officer releases | `completed` | `releaseDocument` | `Document Released` |
| Officer declines / 3-day expiry | `declined` | `declineDocument` / `CleanupStalePendingDocumentsJob` | `Document Declined` |
| Admin / integrity guard | `frozen` | `freezeDocument` / `IntegrityManager` | `ADMIN: Document frozen.` / `System Auto-Freeze` |
| Admin restores | previous status | `unfreezeDocument` / `autoResolveDocument` | `ADMIN: Document unfrozen.` / `ADMIN: Document Unfrozen & Restored` |

Tracking codes are `DEPED-` + 10 uppercase hex characters.

---

## 4. The Cryptographic Ledger

Implemented in [IntegrityManager](../src/Core/IntegrityManager.php). Details and rationale: [CRYPTOGRAPHIC_LOGS_EXPLAINED.md](CRYPTOGRAPHIC_LOGS_EXPLAINED.md); plain-language version: [HASH_VERIFICATION_LAYMAN_GUIDE.md](HASH_VERIFICATION_LAYMAN_GUIDE.md); diagram: [laravel-era/HASH_CHAIN_VISUALIZATION.md](laravel-era/HASH_CHAIN_VISUALIZATION.md) (logic unchanged).

- **State hash:** SHA-256 over the document's tracking code, title, submitter info, district, department, purpose ID and finalized route.
- **Signature:** the acting user's Ed25519 signature over `action|stateHash`. The private key is stored encrypted (AES-256-CBC, key derived from the user's PIN with Argon2id) and only decrypted in memory for the signing call.
- **Block hash:** SHA-256 of `[documentId, userId, action, ISO timestamp, previousHash, stateHash, signature]`; the first entry's previous hash is `genesis_hash`.
- **Snapshot:** each entry also stores the document row as JSON (`document_snapshot`), which **Auto-resolve** restores from.
- **Per-document chains (micro-sharding):** every document has its own chain, so verifying one document reads only its own entries (Kim & Kim, 2024).
- **Active guard:** before any action, `DocumentPolicy` recomputes the live state hash and compares it with the last sealed one; a mismatch blocks the action and freezes the document.
- **Key rotation:** public keys are versioned in `user_public_key_histories` (`activated_at` / `deactivated_at`), so signatures made before a reset still verify.
- **Quantum outlook:** [QUANTUM_SAFETY.md](QUANTUM_SAFETY.md).

---

## 5. Route Suggestions

The Laravel version used a TF-IDF keyword model. The PHP version uses a simpler, deterministic approach:

- Each purpose in `purposes` has a `suggested_route` (JSON list of departments). The officer's route editor is pre-filled with it.
- When an officer finalizes a route, it becomes the purpose's new `suggested_route` (route learning). For official purposes, the guest's own unit is not learned if it was the first step.
- Guests who type a custom purpose are filed under a single **Others** purpose so the table does not grow without limit; the officer builds the route by hand.

---

## 6. Background Worker & Scheduler

[console.php](../console.php) is a long-running worker started by `composer run dev` / `prod` (or `composer run worker`).

- **Queue:** polls the `jobs` table every 2 s, claims a job atomically (`UPDATE … WHERE reserved_at IS NULL`, checked with `rowCount()`), and runs it in a separate process via [runner.php](../runner.php), at most 2 at a time. Failures go to `failed_jobs`.
- **Jobs:** `GenerateReportJob`, `CreateBackupJob` / `RestoreBackupJob` (via `MysqlCli`, which runs `mysqldump`/`mysql` without a shell), `IntegrityCheckJob`.
- **Scheduler:** DB telemetry sample (5 min), department metrics backfill for the last 2 days (30 min), response-cache and DB-cache garbage collection + navigation log rotation (hourly), metrics rollup and stale-pending expiry (daily).
- **Recycling:** the worker restarts itself after 100 jobs or 256 MB (Linux re-executes itself; on Windows `dev.php` / `prod.php` start a new one).

---

## 7. Data & Performance Notes

- **Pagination:** cursor-based (`created_at` + `id`), with total counts cached for 5 minutes.
- **Windowless queries:** "latest log per document" uses `MAX() … GROUP BY` backed by the composite `idx_log_category (user_id, action_category, document_id, created_at)` index instead of window functions.
- **Index-friendly filters:** date filters are ranges (`col >= day AND col < day + 1`) rather than `DATE(col) = day`.
- **Reports** are streamed to the browser with OpenSpout and never written to disk; **Past Reports** re-run the stored filters, bounded by the job's creation time so the result stays the same.
- **Schema changes** go in [database.sql](../database.sql) and, for existing installs, [scripts/migrate.php](../scripts/migrate.php).

---

## 8. Glossary

- **Hash chain:** a list of records where each one includes the previous record's fingerprint, so changing one breaks every later link.
- **Micro-sharding:** one independent chain per document instead of one global chain.
- **Non-repudiation:** a signer cannot later deny an action they signed.
- **Optimistic locking:** detecting conflicting updates with a version number instead of holding database locks.
- **PJAX:** fetching a page with JavaScript and swapping only the main content, so the browser does not reload scripts and styles.
- **State hash:** a fingerprint of a document's important fields at one moment.
