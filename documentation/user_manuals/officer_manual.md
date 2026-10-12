# DepEd Iligan DTS — Records Officer User Manual

**Target Audience:** Records Officers (`officer` role)  

---

## 1. Overview & Role Summary

As a **Records Officer** (`officer`), you are the gateway for every document entering and leaving the DepEd Division of Iligan City. You take in public submissions, confirm the department route, decline incomplete submissions, receive folders back from departments, and release finished documents to their owners.

---

## 2. What You Can Access

| Page | URL | What it is for |
| :--- | :--- | :--- |
| **Document Intake** (home) | `/intake` | Find a submission by tracking code or QR scan; list of documents you handled in the last 4 weeks (filter by date, status, purpose, submitter, search). |
| **Manage Document Route** | `/documents/{id}/manage` | Review a pending submission, adjust its route, **Finalize Route** or **Decline Document**. |
| **Tasks** | `/tasks` | Documents currently at the Records Unit's own processing step (officers can process these like staff). |
| **Completed** | `/tasks/completed` | Documents you finished processing. |
| **Releasing** | `/releasing` | Documents marked `ready_for_release`; release them to the owner. |
| **Statistics** | `/statistics` | Department charts, released-documents table, **Generate Report** (XLSX) and **Past Reports**. |
| **Document details / Hash chain** | `/documents/{tracking_code}`, `/documents/{tracking_code}/hash-chain` | Full history and the signed audit trail of any document. |
| **Admin pages, user management** | — | Not available (admins only). |

---

## 3. First Login: Set Up Your Signing PIN

Every action that changes a document (finalize, decline, receive, complete, release) is signed with your personal **Ed25519 digital signature**.

1. Sign in at `/login` with your username (e.g. `officer.recordsunit.dts`) and password.
2. On your first login a window titled **Action Required: Setup Your Digital Signature** appears over the page. You cannot perform any action until you finish it.
3. Enter a **Security PIN of at least 6 characters** and confirm it.
4. Click **Generate & Secure Key**. The system creates your key pair and stores your private key encrypted with your PIN.

> [!IMPORTANT]
> Your PIN cannot be recovered. If you forget it, ask an administrator to **Reset** your digital signature in User Management, then set a new PIN at your next login.

**PIN prompts:** the first signed action in a browser tab asks for your PIN. The tab then remembers it (in `sessionStorage`) until you close the tab, so later actions in the same tab do not ask again. Close the tab when you leave a shared computer.

---

## 4. Workflows

### 4.1 Intake & Route Finalization

```
[Guest submits online] ──> status: pending ──> [Officer: Finalize Route + PIN] ──> status: in_transit ──> first department
```

1. A guest submits a document on the public page and receives a tracking code (`DEPED-` followed by 10 characters) plus a **printable tracking form** with a QR code. (Anyone can reopen the form at `/documents/{tracking_code}/print-tracking-form`.)
2. On **Document Intake** (`/intake`), type the code in **Add Document by Tracking Code** and click **Find**, or click **Scan QR Code** and scan the form. This opens **Manage Document Route**.
3. Check the submitter, purpose and requirements.
4. Review the route. It is pre-filled from the purpose's **suggested route**, which is simply the last route an officer finalized for that purpose. Guests who type their own purpose are filed under **Others**, and you choose the departments. Drag departments to reorder, add or remove them.
5. Click **Finalize Route** and enter your PIN. The document becomes `in_transit` to the first department, and the log records **"Accepted and Document Routing finalized"**.

> [!NOTE]
> Pending submissions that are not finalized within **3 days** are declined automatically by the system ("System Auto-Cleanup: Pending document expired…"), with a system-sealed log entry.

### 4.2 Declining a Submission

1. On **Manage Document Route**, click **Decline Document**.
2. Type the reason (e.g. *Missing principal's endorsement*), enter your PIN and confirm.
3. The status becomes `declined` and the log records **"Document Declined"** with your reason.

### 4.3 Receiving Folders (QR Scan)

Use **Scan QR Code** (camera or typed code) whenever a folder arrives at the Records Unit:

- If the Records Unit is a step in the route, the document is **Received** and appears in **Tasks**; complete it like any department (see the [Staff Manual](staff_manual.md)).
- If all department steps are done, the document becomes `ready_for_release` and the log records **"Ready for Releasing"**.

The scanner tells you why a document cannot be received (e.g. still pending intake, being processed by another department, already in the release list, already released).

### 4.4 Releasing Completed Documents (`/releasing`)

1. Open **Releasing**. It lists documents with status `ready_for_release`.
2. Hand the folder to the owner, click **Release** on that row and enter your PIN.
3. The status becomes `completed` and the log records **"Document Released"** ("The document has been released to the client."). The system does not record the recipient's name; the signed entry ties the release to you.

### 4.5 Reports (`/statistics`)

1. Open **Statistics** and filter the released-documents table (date, purpose, submitter, search) if needed.
2. Click **Generate Report**. The queue worker builds it in the background; progress shows in a modal and a notification appears when it is ready.
3. Download it from the modal or later from **Past Reports**. Reports are **Excel (XLSX)** files named `released-documents-<timestamp>-<count>docs[-filters].xlsx`. They contain only documents released by your department before you clicked Generate.

---

## 5. Document Statuses

| Status | Meaning |
| :--- | :--- |
| `pending` | Submitted by a guest, waiting for intake. |
| `in_transit` | Route finalized or a step completed; travelling to the next department. |
| `processing` | Received by a department and being worked on. |
| `ready_for_release` | All steps done; back at the Records Unit. |
| `completed` | Released to the owner. |
| `declined` | Declined by an officer, or expired after 3 days pending. |
| `frozen` | Locked by an administrator or the integrity guard pending investigation. |

---

## 6. Troubleshooting & FAQs

- **The QR camera does not open.** Allow camera access for the DTS site in your browser. Browsers only allow the camera on HTTPS or `localhost`, so use the `https://` address. You can always type the tracking code instead.
- **"Action Denied: … integrity" / the document is frozen.** The document's data no longer matches its signed history. Ask an administrator to investigate and **Auto-resolve** it.
- **"This document was just modified by another user. Please refresh."** Someone acted on the same document a moment before you. Refresh and check its new status.
- **A report never finishes.** The queue worker may not be running. Ask the administrator; it starts with `composer run dev` / `composer run prod`.
