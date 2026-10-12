# DTS User Guide & Role-Based Workflows

## Summary
A comprehensive manual for all users of the Document Tracking System (DTS). This document outlines the distinct workflows for **Administrators**, **Records Officers**, and **Department Staff**. It highlights the transition from traditional manual tracking to the new, digitally-signed workflow, ensuring all users can navigate their respective dashboards and perform their duties with confidence.

## Table of Contents
1. [What Changed? The New Digital Workflow](#1-what-changed-the-new-digital-workflow)
2. [Workflow: Records Officers (The Gatekeepers)](#2-workflow-records-officers-the-gatekeepers)
3. [Workflow: Department Staff (The Processors)](#3-workflow-department-staff-the-processors)
4. [Workflow: Administrators (The Overseers)](#4-workflow-administrators-the-overseers)
5. [Common Security Procedures](#5-common-security-procedures)
6. [Glossary of Terms](#6-glossary-of-terms)

---

## 1. What Changed? The New Digital Workflow

The DTS is more than just a digital logbook. It introduces a high-security, physical-digital hybrid workflow:

- **Traditional**: Documents are moved between desks with a manual log sheet that can be lost or tampered with.
- **DTS Workflow**: 
    1.  **AI Routing**: Officers are assisted by AI when deciding where a document should go.
    2.  **QR Handoff**: Every time a document changes hands physically, a QR code is scanned.
    3.  **Security PIN**: Significant actions (like finalizing a route or completing a task) require a **Security PIN** to generate an unbreakable digital signature.
    4.  **Non-Linear Routing**: Departments can dynamically request a "Return" to a previous step if corrections are needed.

---

## 2. Workflow: Records Officers (The Gatekeepers)

Records Officers are the primary controllers of the system. While they act as gatekeepers for intake and releasing, they also have full document processing capabilities similar to department staff.

### Key Responsibilities:
- **Intake Management**: Reviewing new submissions from guests. Officers can "Accept" (finalize the route) or "Decline" with a reason.
- **Route Finalization**: The officer can accept the AI's suggested route or manually override it. This action requires a **Security PIN**.
- **Full Document Processing**: Records Officers can receive, process, and complete tasks for documents assigned to the Records Unit.
- **Final Releasing**: Once a document has finished its processing journey, the officer performs the final QR scan to mark it as "Ready for Release" and then "Released."
- **Return Requests**: Viewing and managing requests from departments that want to send a document back.

### Dashboard Views:
- **Intake Queue**: All incoming guest submissions.
- **Releasing Queue**: Documents that have finished their processing and are waiting for the guest.
- **Officer Tasks**: Personal and unit tasks currently being processed by the Records Office.
- **Completed Tasks**: A historical list of all documents successfully processed or released by the Records Office.
- **Statistics**: Access to real-time throughput and load distribution reports.

---

## 3. Workflow: Department Staff (The Processors)

Staff members are the primary engines of the system, performing the actual processing of documents.

### Key Responsibilities:
- **Physical Receipt**: When a document arrives at a department, staff must scan the QR code using the **"Scan"** feature. This marks the document as "Received" and starts the processing timer.
- **Task Completion**: Once the work is done, staff click **"Complete Task"** and enter their **Security PIN** to sign the action.
- **Return Requests**: If a document is missing info or has errors, staff can trigger a **"Return Request"** to send it back to a previous unit.

### Dashboard Views:
- **My Tasks**: A live queue of all documents currently being processed by the department.
- **Completed Tasks**: A historical list of all documents handled by the unit.
- **Statistics**: Local analytics showing the department's turnaround time and current workload.

---

## 4. Workflow: Administrators (The Overseers)

Administrators ensure the system's technical health and data integrity.

### Key Responsibilities:
- **User Management**: Creating and managing user accounts, linking them to departments, and resetting security keys if needed.
- **Integrity Monitoring**: Viewing the **"Integrity Monitor"** to check for "State Discrepancies" or broken hash chains.
- **System Health**: Monitoring database performance metrics (connections, slow queries) and viewing system debug logs.
- **Backup & Recovery**: Centralized control for the **"Safety Net"** system, providing automated recovery points and on-demand database snapshots.
- **Document Freezing**: In cases of suspected tampering, admins can "Freeze" a document, disabling all actions on it until an investigation is complete.

### Dashboard Views:
- **Admin Dashboard**: A high-level view of system-wide throughput, bottlenecks, and submission trends.
- **System Health**: Real-time graphs of server performance.
- **Backup Manager**: Centralized interface for managing system snapshots and restorations.
- **User List**: Management of the organization's workforce.

---

## 5. Common Security Procedures

### Initializing Your Security Key
When you first log in, you will be prompted to set up your **Security Key**.
1.  **Set a PIN**: This PIN is used to encrypt your private key. Never share this with anyone outside your department.
2.  **Generate Keys**: The system will create your unique Public/Private key pair. 
3.  **Digital Identity**: From this moment on, every significant action you take will be cryptographically signed with your identity.

### Handling an "Integrity Error"
If the system detects that a document has been modified outside the application (e.g., direct database editing), it will trigger an **Auto-Freeze**. 
- **Staff/Officer**: You will see a "Frozen" status and won't be able to perform actions. Notify your Admin immediately.
- **Admin**: Use the **Integrity Monitor** to see which field was modified and use the **Rebuild Chain** utility if necessary/resolved.

---

## 6. Glossary of Terms

*   **Finalize Route**: The process where a Records Officer locks in the sequence of departments a document will visit.
*   **Freeze/Unfreeze**: An administrative action to lock a document to prevent any further status changes.
*   **In Transit**: A status indicating the document is physically moving between departments.
*   **Intake**: The initial reception of a document into the system.
*   **Processing**: A status indicating a department is currently working on the document.
*   **QR Scanning**: Using a camera to read the tracking code from a physical paper to update its digital status.
*   **Return Request**: A non-linear move that sends a document back to a previous processing unit.
*   **Security PIN**: A 4-6 digit code used to authorize and sign your digital actions.
*   **Signature**: A mathematical proof that *you* performed a specific action.
