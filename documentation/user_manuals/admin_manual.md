# DepEd Iligan DTS — System Administrator User Manual

**Target Audience:** System Administrators (`admin` role)  

---

## 1. Overview & Role Summary

As a **System Administrator** (`admin`), you possess full control and global visibility over the DepEd Division of Iligan City Document Tracking System (DTS). Your primary responsibilities include user account management, cryptographically monitoring system integrity, executing data backups/restores, analyzing division-wide processing bottlenecks, and resolving auto-frozen document tamper alerts.

---

## 2. Role-Based Access Control (RBAC) Permissions

| Feature / Resource | Permission Level | Description |
| :--- | :--- | :--- |
| **Admin Dashboard** (`/admin-dashboard`) | Full Access | Live system throughput, decline trends, department load vs. step-time analytics. |
| **User Management** (`/users`) | Full Access | Create, edit, deactivate, delete users, and reset Ed25519 digital signature key pairs. |
| **System Overview & Health** (`/system-overview`) | Full Access | Run audit jobs, view DB performance metrics, clear personal/system caches, delete failed background jobs. |
| **Integrity & Tamper Control** (`/all-documents`, `/system-health/*`) | Full Access | View all document hash chains, freeze/unfreeze documents, execute snapshot-based Auto-Resolve. |
| **System Backups** (`/system/backups`) | Full Access | Generate manual database backups, restore database snapshots, download/delete backup archives. |
| **Global Document Visibility** (`/documents/{tracking_code}`) | Full Access | Unrestricted view and hash-chain audit access across all division documents. |
| **Department Task Execution** (`/intake`, `/tasks`) | View Only | Direct task processing is handled by Records Officers and Department Staff. |

---

## 3. First-Time Login & Security Key Setup

Every user action that mutates document state requires a cryptographically secure **Ed25519 Digital Signature**. On your initial login, you must initialize your personal signing key.

### Step-by-Step Initialization:

1. **Access Login Portal:** Navigate to `/login` using your web browser.
2. **Authenticate:** Enter your assigned admin email address and password.
3. **Security Key Setup Prompt:** If you have not set up your security key, the system automatically redirects you to the Security Key Setup screen (`/security-key`).
4. **Create Your 6-Digit PIN:**
   - Enter a secure 6-digit numeric PIN.
   - *Technical Note:* The system derives an encryption key from your PIN using Argon2/Sodium (or SHA-256) to encrypt your newly generated Ed25519 private key before storing it in the database (`users.private_key`).
5. **Confirm Initialization:** Click **Set Security Key**. Once completed, your public key is published to `users.public_key` and logged in `user_public_key_histories`.

> [!IMPORTANT]
> Your Security PIN is never stored in plain text and cannot be recovered by the system. If you forget your PIN, another Administrator must reset your digital signature key pair via the User Management panel.

---

## 4. Administrator Operations & Workflows

### 4.1 Managing User Accounts (`/users`)

1. **Creating New Accounts:**
   - Navigate to **Users Management** -> **Create User** (`/users/create`).
   - Fill in full name, email, role (`admin`, `officer`, or `staff`), and assign a division department.
   - Click **Save User**.
2. **Resetting Digital Signatures:**
   - If a user loses their PIN or compromises their key, go to `/users`, locate the user, and click **Reset Signature**.
   - *Security Policy:* The system archives the user's old public key into `user_public_key_histories` with an `activated_at` / `deactivated_at` timeframe. This ensures older signed documents remain cryptographically verifiable while invalidating the compromised key for future signatures.

### 4.2 System Health & Integrity Monitoring (`/system-overview`)

1. **Running a Full System Audit:**
   - Navigate to `/system-overview` and click **Run Integrity Check**.
   - An asynchronous queue job ([IntegrityCheckJob](file:///home/mirkolouis/Documents/DTS%20Prototype/src/Jobs/IntegrityCheckJob.php)) evaluates every document's state hash against its immutable log chain (`document_logs`).
2. **Monitoring Real-Time DB Metrics:**
   - View active database connections, average query response times, and slow query logs fetched via [DatabasePerformanceService](file:///home/mirkolouis/Documents/DTS%20Prototype/src/Services/DatabasePerformanceService.php).
   - Export performance metrics to CSV for auditing.

### 4.3 Handling Tampered Documents & Auto-Resolve

When raw database tampering is detected (e.g., unauthorized SQL updates bypassing application logic), the JIT **Active Guard** instantly blocks processing and triggers an **Auto-Freeze**.

```
[Database Tampering] ──> [Active Guard Check Fails] ──> [Status: FROZEN] ──> [Admin Auto-Resolve]
```

1. **Identifying Frozen Documents:**
   - Frozen documents appear with a prominent warning badge on `/all-documents` and `/admin-dashboard`.
2. **Reviewing Hash Discrepancies:**
   - Open the document details page (`/documents/{tracking_code}`) and view the **Digital Hash Chain** (`/documents/{tracking_code}/hash-chain`).
   - Compare the current live SHA-256 state hash against the last logged `document_state_hash`.
3. **Executing Snapshot Auto-Resolve:**
   - On the document page, click **Auto-Resolve Document**.
   - The system retrieves the clean `document_snapshot` (JSON) stored inside the last valid `document_logs` record.
   - It overwrites the corrupted database row with the authentic snapshot data and unfreezes the document.
   - *Append-Only Rule:* The Auto-Resolve action logs a new entry (`ADMIN: Document Unfrozen & Restored`) signed by your Admin key, preserving full audit-trail transparency.

### 4.4 Managing System Backups (`/system/backups`)

1. **Creating a Backup:** Click **Create Backup Now**. The background job [CreateBackupJob](file:///home/mirkolouis/Documents/DTS%20Prototype/src/Jobs/CreateBackupJob.php) generates a compressed SQL dump in `storage/backups/`.
2. **Restoring a Snapshot:** Select a backup file and click **Restore**. The system executes [RestoreBackupJob](file:///home/mirkolouis/Documents/DTS%20Prototype/src/Jobs/RestoreBackupJob.php) to restore database tables.
3. **Downloading Archives:** Backup files can be downloaded directly for off-site storage.

---

## 5. Troubleshooting & Admin FAQs

- **Q: What happens if the background worker (`console.php`) stops?**  
  *A:* Queue items (report exports, integrity checks, backups) will remain in `queued` status. Restart the worker from terminal using `composer run worker` (or `php console.php`).
- **Q: Why does clearing cache fail on certain pages?**  
  *A:* Caching uses Symfony Cache backed by database/filesystem storage. Click **Clear System Cache** on `/admin-dashboard` or `/clear-personal-cache` to invalidate stale cached analytics.
