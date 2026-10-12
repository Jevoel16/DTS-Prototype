# DepEd Iligan DTS — Department Staff User Manual

**Target Audience:** Department Staff (`staff` role)  

---

## 1. Overview & Role Summary

As **Department Staff** (`staff`), you handle the domain-specific review, actioning, and endorsement of official documents routed to your specific division department (e.g., Accounting, Budget, Personnel, Legal, ODS). Your key responsibilities include receiving physical document folders delivered to your desk, executing internal actions, providing detailed action remarks, digitally signing completed tasks, and routing paperwork to the next department in line.

---

## 2. Role-Based Access Control (RBAC) Permissions

| Feature / Resource | Access Level | Description |
| :--- | :--- | :--- |
| **Department Tasks** (`/tasks`) | Primary Landing | View and process active documents currently routed to your department. |
| **Global QR Scanner** (`/documents/scan`) | Department-Scoped | Scan QR codes to log physical receipt of documents assigned to your department. |
| **Task Completion** (`/tasks/{id}/complete`) | Full Access | Add action remarks, enter Security PIN, and advance document to next step. |
| **Completed Tasks History** (`/tasks/completed`) | Full Access | Review past tasks completed by your department. |
| **Department Analytics** (`/statistics`) | Read Access | View turnaround time (TAT) charts and request custom statistics exports. |
| **Document Visibility** | Department-Scoped | Restricted to documents whose finalized route includes your department. |
| **Intake / Releasing Portals** | No Access | Restricted to Records Officers (`officer` role). |
| **Admin & User Management** | No Access | Restricted to System Administrators (`admin` role). |

---

## 3. First-Time Login & Security Key Setup

Every task completion or receipt log generates a cryptographically binding **Ed25519 Digital Signature**. On your first login, you must set up your secret Security PIN.

### Step-by-Step Initialization:

1. Navigate to `/login` and sign in with your email and password.
2. You will be automatically directed to the **Security Key Setup** screen (`/security-key`).
3. Choose a secret **6-Digit Security PIN**.
4. Click **Set Security Key**.
5. The system generates your unique cryptographic keypair. Your private key is encrypted with your PIN and stored securely.

> [!IMPORTANT]
> Do not share your 6-digit PIN with anyone. Every document action signed with your PIN is permanently linked to your identity in the immutable audit trail.

---

## 4. Operational Workflows & Step-by-Step Guide

### 4.1 Viewing Assigned Tasks (`/tasks`)

```
[Physical Folder Delivered] ──> [Scan QR Code / View /tasks] ──> [Perform Action] ──> [Complete Task + PIN] ──> [Routed to Next Dept]
```

1. Navigate to **My Department Tasks** (`/tasks`).
2. The task table lists all pending documents currently waiting at your department's step in the routing chain.
3. Items display the tracking code, title, submitter info, current step index, and total elapsed time.

### 4.2 Receiving an Incoming Physical Folder (`/documents/scan`)

When a physical document folder is delivered to your department:

1. Click the **Scan QR Code** button in the header (or open the scanner modal).
2. Point your camera at the QR code on the document's tracking sheet.
3. The scanner identifies the document. Click **Receive Document**.
4. Enter your **6-Digit Security PIN** and click confirm.
5. The system logs a `Received at [Department Name]` entry signed with your cryptographic key.

### 4.3 Actioning & Completing a Task (`/tasks/{id}/complete`)

After performing your required office work (e.g., verifying budget allocation, signing endorsement, encoding records):

1. On `/tasks`, locate the document and click **Complete Task** (or open document details at `/documents/{tracking_code}`).
2. In the completion modal:
   - Enter detailed **Action Remarks** summarizing the work done (e.g., *Approved budget allocation of ₱15,000. Forwarded to Accounting for obligation*).
   - Enter your **6-Digit Security PIN**.
3. Click **Submit & Route to Next Step**.
4. **What happens under the hood:**
   - The Active Guard checks document integrity before proceeding.
   - The system locks the document row using pessimistic locking (`SELECT ... FOR UPDATE`).
   - It records a signed log entry, increments the document's `current_step` counter, and automatically updates the active department pointer to the next office in the routing chain.

### 4.4 Reviewing Completed Tasks (`/tasks/completed`)

1. Navigate to `/tasks/completed`.
2. View historical documents previously processed and signed by your department, along with timestamps, action remarks, and total processing duration.

---

## 5. Troubleshooting & FAQs

- **Q: A document folder was delivered to me, but it doesn't show up on `/tasks`.**  
  *A:* Check the current routing step. The previous department may not have marked their task as complete yet in the DTS software. Scan the QR code to verify the current active department.
- **Q: I get "Action Denied: You lack permissions for this document".**  
  *A:* Staff members can only process documents currently positioned at their department's step index. If the document is currently assigned to another department, processing is restricted by system security policies.
- **Q: What should I do if I forget my 6-digit PIN?**  
  *A:* Contact your System Administrator to request a Digital Signature Reset. They will reset your signature key while safely archiving your past signed logs.
