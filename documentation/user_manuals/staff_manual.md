# DepEd Iligan DTS — Department Staff User Manual

**Target Audience:** Department Staff (`staff` role)  

---

## 1. Overview & Role Summary

As **Department Staff** (`staff`), you handle documents routed to your office (e.g. Accounting, Budget, Personnel, Legal, SDS Office). You receive the physical folder in the system when it arrives, do your office's work, and mark the step complete so the document moves to the next office on its route.

---

## 2. What You Can Access

| Page | URL | What it is for |
| :--- | :--- | :--- |
| **Tasks** (home) | `/tasks` | Documents currently being processed by your department (excludes documents still in transit and those awaiting release). Filter by date, status, purpose, submitter, search. |
| **Scan QR Code** | header button | Receive a folder that has arrived at your office. |
| **Completed** | `/tasks/completed` | Documents you completed in the last 4 weeks. |
| **Statistics** | `/statistics` | Your department's load, throughput and processing-time charts; **Generate Report** (XLSX) and **Past Reports**. |
| **Document details** | `/documents/{tracking_code}` | Only for documents whose route includes your department. |
| **Intake, Releasing, Admin pages** | — | Not available (Records Officers / Administrators only). |

---

## 3. First Login: Set Up Your Signing PIN

Every receive and complete action is signed with your personal **Ed25519 digital signature**.

1. Sign in at `/login` with your department username (e.g. `staff.budgetunit.dts`) and password.
2. A window titled **Action Required: Setup Your Digital Signature** appears. You cannot act on documents until you finish it.
3. Enter a **Security PIN of at least 6 characters**, confirm it, and click **Generate & Secure Key**.

> [!IMPORTANT]
> Do not share your PIN. Everything signed with it is permanently linked to you in the audit trail. If you forget it, ask the administrator to **Reset** your digital signature; you then set a new PIN at your next login.

**PIN prompts:** the first signed action in a browser tab asks for your PIN; the tab remembers it until you close it.

---

## 4. Workflows

```
[Folder arrives] ──> [Scan QR Code + PIN: "Received", status processing] ──> [Do the work] ──> [Complete + PIN: "Processing Complete"] ──> in_transit to next office
```

### 4.1 Receiving a Folder

1. When a folder is delivered, click **Scan QR Code** in the header and scan the QR code on its tracking form (or type the tracking code).
2. Confirm and enter your PIN.
3. The document's status becomes `processing` at your department, it appears in **Tasks**, and the log records **"Received"** ("Document received by <your department>.").

The scanner explains if a folder cannot be received yet, e.g. it is still pending intake at the Records Unit or is currently being processed by another office.

### 4.2 Completing Your Step

1. Do your office's work on the physical document.
2. On **Tasks**, click the complete action for the document and enter your PIN.
3. The log records **"Processing Complete"** with "In transit to <next office>", the step counter advances, and the document becomes `in_transit` to the next office. After the last office it goes back to the Records Unit for release.
4. Hand the folder to the next office.

> [!NOTE]
> There is no free-text remarks box when completing a step; the signed log entry records who completed it, when, and where it goes next.

### 4.3 Completed Tasks (`/tasks/completed`)

Lists the documents you completed in the last 4 weeks, with their current status, so you can answer follow-up questions.

---

## 5. Troubleshooting & FAQs

- **A folder was delivered but is not in Tasks.** Scan it first: documents appear in Tasks only after your office receives them. If the scanner says another office is still processing it, that office has not completed its step yet.
- **"Action Denied" when opening or acting on a document.** Staff can only act on documents at their own department's step, and only see documents whose route includes their department.
- **"This document was just modified by another user. Please refresh."** A colleague acted on it first; refresh to see the current status.
- **I forgot my PIN.** Ask the System Administrator for a signature reset. Your past signatures stay valid because your old public key is archived.
