# DepEd Iligan DTS — Records Officer User Manual

**Target Audience:** Records Officers (`officer` role)  

---

## 1. Overview & Role Summary

As a **Records Officer** (`officer`), you serve as the central gateway for all official documents entering and exiting the DepEd Division of Iligan City. Your responsibilities include intaking public submissions, validating document requirements, defining and finalizing departmental routing paths, scanning incoming physical paperwork, declining incomplete submissions, and conducting final document release to owners.

---

## 2. Role-Based Access Control (RBAC) Permissions

| Feature / Resource | Access Level | Description |
| :--- | :--- | :--- |
| **Intake Portal** (`/intake`) | Primary Landing | Process guest/walk-in submissions, configure routing steps, finalize routes. |
| **Releasing Portal** (`/releasing`) | Full Access | Review fully processed documents, capture recipient details, mark as released. |
| **Global QR Scanner** (`/documents/scan`) | Full Access | Scan QR codes on physical documents to log intake/receipt actions. |
| **Document Management** (`/documents/{id}/manage`) | Full Access | Edit finalized routing paths and document metadata prior to completion. |
| **Document Finalize & Decline** | Full Access | Complete document lifecycles or decline invalid submissions with remarks. |
| **Division Statistics** (`/statistics`) | Full Access | View processing TAT charts and generate background PDF/Excel exports. |
| **Global Document Visibility** | Full Access | View details and audit hash chains for any document in the system. |
| **Admin Panel & User Setup** | No Access | Restricted to System Administrators (`admin` role). |

---

## 3. First-Time Login & Security Key Setup

All state-mutating actions (Intake, Scan, Finalize, Decline, Release) require an **Ed25519 Digital Signature** to guarantee non-repudiation.

### Step-by-Step Initialization:

1. Navigate to `/login` and sign in with your officer credentials.
2. The system automatically redirects first-time users to `/security-key`.
3. Enter your secret **6-Digit Security PIN**.
4. Click **Set Security Key**. The system generates your cryptographic Ed25519 keypair. Your private key is encrypted with your PIN before being stored safely in the database.

> [!WARNING]
> Keep your 6-digit PIN secret. You will be prompted to enter this PIN every time you intake, scan, decline, or release a document.

---

## 4. Operational Workflows & Step-by-Step Guide

### 4.1 Intaking & Finalizing New Submissions (`/intake`)

```
[Public/Walk-in Document] ──> [/intake Portal] ──> [Configure Route] ──> [Enter PIN & Sign] ──> [Print QR Tracking Form]
```

1. **Access Intake Portal:** Go to `/intake`. Unprocessed submissions submitted by guests or walk-ins appear in the queue.
2. **Review Document Details:** Click **Manage Route** on a pending document.
3. **Verify Purpose & Requirements:** Inspect submitter details, purpose, and attached compliance items.
4. **Configure Department Route:**
   - Review the default route suggested by the system based on document keywords.
   - Adjust department order (e.g., *Records -> Administrative -> Budget -> Accounting -> ODS*).
5. **Finalize & Sign:**
   - Click **Finalize & Accept Document**.
   - Prompt Modal: Enter your **6-Digit PIN**.
   - The system calculates the initial SHA-256 state hash, generates your Ed25519 signature, and logs `Accepted and Document Routing finalized` in `document_logs`.
6. **Print Tracking Form:** Click **Print Tracking Form** to generate a printable PDF containing the tracking code and scannable QR code (`/documents/{tracking_code}/print-tracking-form`). Attach this form to the physical paper folder.

### 4.2 Scanning & Receiving Physical Documents (`/documents/scan`)

1. Open the QR Scanner modal from the top navigation bar or navigate to `/intake`.
2. Point the camera scanner at the document's printed QR code (or enter the tracking code manually).
3. Confirm document details in the pop-up modal.
4. Enter your **Security PIN** and click **Receive Document**.
5. The system locks the database row (`SELECT ... FOR UPDATE`), records a `Received at Records` log entry with your digital signature, and updates the document status.

### 4.3 Declining Incomplete Submissions

1. On the document management page (`/documents/{id}/manage`), click **Decline Document**.
2. Select a decline reason or type explicit notes in the remarks box (e.g., *Missing Principal endorsement signature*).
3. Enter your **Security PIN** and confirm.
4. The system updates status to `declined` and logs the signed decline record.

### 4.4 Releasing Completed Documents (`/releasing`)

When a document finishes all departmental routing steps, its status advances to `ready_for_release` and it appears in the Releasing Portal.

1. Navigate to **Releasing** (`/releasing`).
2. Locate the physical document folder returned by the final department.
3. Click **Release Document**.
4. In the releasing modal:
   - Record the name and contact of the person receiving the physical document.
   - Add optional release notes.
   - Enter your **Security PIN**.
5. Click **Confirm Release**. The document status updates to `completed` / `released`, and a final signed log entry is generated.

### 4.5 Exporting Statistical Reports (`/statistics`)

1. Navigate to `/statistics`.
2. Select desired date ranges and report formats (PDF or Excel).
3. Click **Generate Report**. The system dispatches an asynchronous background job ([GenerateReportJob](file:///home/mirkolouis/Documents/DTS%20Prototype/src/Jobs/GenerateReportJob.php)).
4. Monitor export progress in the floating report progress modal and download the completed file.

---

## 5. Troubleshooting & FAQs

- **Q: The QR camera scanner is not opening.**  
  *A:* Ensure your web browser has camera permissions granted for the DTS domain. Alternatively, type the tracking code into the manual input box.
- **Q: An error says "Action Denied: Document failed integrity check".**  
  *A:* The document's SHA-256 state hash does not match the ledger. The document has been auto-frozen by the Active Guard. Notify a System Administrator to run Auto-Resolve.
