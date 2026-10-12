# DepEd Iligan DTS — System Administrator User Manual

**Target Audience:** System Administrators (`admin` role)  

---

## 1. Overview & Role Summary

As a **System Administrator** (`admin`), you manage user accounts, watch division-wide workload, verify the integrity of every document's signed history, respond to tampering, and keep backups. Day-to-day document processing (intake, receiving, releasing) is done by Records Officers and Department Staff, not by admins.

---

## 2. What You Can Access

| Page | URL | What it is for |
| :--- | :--- | :--- |
| **Admin Dashboard** (home) | `/admin-dashboard` | Charts: current load, throughput, decline trends, status distribution, processing hotspots, submission districts, average step time, department load vs. time, peak intake hours. **Clear Cache** button. |
| **All Documents** | `/all-documents` | Every document with its status and integrity state; open any document. |
| **System Overview** | `/system-overview` | Failed jobs, cache status, database performance (with **Export CSV**), **Run Verification** with **Auto-resolve** / Rebuild Chain on the results, **Freeze All**. |
| **Backup Manager** | `/system/backups` | Create, download, restore and delete database backups. |
| **Users** | `/users` | Create, edit and delete accounts; **Reset** a user's digital signature. |
| **Document details / Hash chain** | `/documents/{tracking_code}`, `.../hash-chain` | Any document's history and signed audit trail. |
| **Intake, Tasks, Releasing** | — | Not available to admins (officer/staff pages). |

---

## 3. First Login: Set Up Your Signing PIN

Admin actions that change documents (freeze, unfreeze, auto-resolve, Freeze All) are signed with your **Ed25519 digital signature**.

1. Sign in at `/login` with username `admin` (or your own admin account).
2. The window **Action Required: Setup Your Digital Signature** appears. Enter a **PIN of at least 6 characters**, confirm it, and click **Generate & Secure Key**.
3. Your private key is encrypted with a key derived from your PIN (Argon2id) and stored in `users.private_key`; your public key goes to `users.public_key` and is recorded in `user_public_key_histories`.

> [!IMPORTANT]
> PINs cannot be recovered. If an admin forgets theirs, another admin must reset it.

---

## 4. Tasks

### 4.1 Managing Users (`/users`)

- **Create:** **Users → Create** (`/users/create`). Enter name, username, password, role (`admin`, `officer`, `staff`) and department.
- **Edit / Delete:** from the users list. There is no "deactivate" option; delete accounts that should no longer sign in.
- **Reset a digital signature:** click **Reset** for a user who forgot their PIN or whose key may be compromised. Their current public key is closed in `user_public_key_histories` (`deactivated_at`), so documents they signed earlier still verify. They set a new PIN at their next login.

### 4.2 Verifying Integrity (`/system-overview`)

1. Click **Run Verification**. A background job (`IntegrityCheckJob`) checks every log's hash chain and signature and compares each document's live data with its last signed state. Progress is shown; you can **Cancel Verification**.
2. Results list broken log entries and documents whose live data differs from the signed history.
3. Fix options:
   - **Rebuild Chain** for a broken log entry: recomputes the hashes from that entry forward and records **"ADMIN: Hash chain rebuilt for Log ID: …"**.
   - **Freeze All:** with your PIN, freezes every document flagged by the last verification (log entry **"ADMIN: Document frozen."** on each).
   - **Auto-resolve** a frozen document (see 4.3).

The integrity guard also checks a document every time someone acts on it. If the data was changed outside the app, the action is blocked and the document is frozen automatically (**"System Auto-Freeze"**). `composer run corrupt` simulates this for demonstrations.

### 4.3 Resolving Frozen Documents

1. Open the document (from **All Documents** or the verification results) and review its **Hash Chain** page to see what changed.
2. Choose:
   - **Auto-resolve** (in the verification results on System Overview): restores the document's data from the snapshot saved in its last valid log entry, unfreezes it, and records **"ADMIN: Document Unfrozen & Restored"** signed by you.
   - **Unfreeze** (on the document page): returns the document to its previous status without restoring data (**"ADMIN: Document unfrozen."**). Use only when the data is known to be correct. The same page has **Freeze** for freezing a single document.

Nothing is ever deleted from the log: every admin action adds a new signed entry.

### 4.4 Backups (`/system/backups`)

1. **Create Database Backup Now** queues a backup. The worker runs `mysqldump` and saves a **zip** in `storage/app/backups/` named `deped_dts_<date>_<time>_<document count>.sql.zip`.
2. **Download** a backup for off-site storage, **Restore** one (replaces the current database contents with the backup), or **Delete** old ones.
3. `php scripts/daily-backup.php` creates a backup from the command line, e.g. from Windows Task Scheduler or cron.

> [!WARNING]
> Restoring overwrites all current data with the backup's contents. Create a fresh backup first.

### 4.5 Failed Jobs & Caches

- **Failed Jobs** on System Overview lists background jobs that threw an error, with the error text. Delete one, or **Clear All Jobs**.
- **Clear Cache** on the Admin Dashboard empties the database cache used for dashboard counts and charts. Each user's pages are also cached for 55 seconds; the personal clear-cache button on the dashboards clears your own.

---

## 5. Troubleshooting & FAQs

- **Reports, backups or verification stay "queued".** The queue worker (`console.php`) is not running. It starts with `composer run dev` / `composer run prod`, or alone with `composer run worker`. Its output is in `storage/logs/worker.log`.
- **A backup failed.** Check **Failed Jobs** for the message. The MySQL client tools (`mysqldump`, `mysql`) must be on the server's `PATH`.
- **Dashboard numbers look stale.** Department metrics are recalculated every 30 minutes by the worker; click **Clear Cache** to refresh cached chart data.
- **Database tuning.** See [Hardware Specs & MySQL Tuning](../HARDWARE_SPECS.md).
