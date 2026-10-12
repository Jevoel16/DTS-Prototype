# DTS System Architecture & Core Logic

## Summary
A comprehensive technical deep-dive into the Document Tracking System (DTS) designed for the **DepEd Division of Iligan City**. This document details the underlying technologies, the end-to-end document lifecycle, the sophisticated cryptographic "Trust Builder" (hashing and Ed25519 signatures), and the AI-driven route prediction engine. It is intended for developers, system architects, and technical stakeholders.

## Table of Contents
1. [Technology Stack & Libraries](#1-technology-stack--libraries)
2. [Document Lifecycle: The Digital Journey](#2-document-lifecycle-the-digital-journey)
3. [The Trust Builder: Cryptographic Ledger](#3-the-trust-builder-cryptographic-ledger)
4. [AI Route Prediction & Machine Learning](#4-ai-route-prediction--machine-learning)
5. [Operational Infrastructure & Analytics Logic](#5-operational-infrastructure--analytics-logic)
6. [Glossary of Terms](#6-glossary-of-terms)

---

## 1. Technology Stack & Libraries

The DTS is built on a modern, high-performance stack optimized for security and scalability.

### Core Technologies
- **PHP 8.3 & Laravel 12**: The primary backend engine. PHP 8.3 provides high-speed execution and native support for the `sodium` cryptographic library. Laravel 12 offers a robust framework for role-based access control, background jobs, and database management.
- **MySQL 8.0**: Chosen for its support of **JSON** data types (used for flexible metadata) and **Window Functions** (used for high-speed, disk-level analytics calculations).
- **Tailwind CSS 4 & Bootstrap 5 (CSS-only)**: A hybrid frontend approach. Tailwind CSS 4 provides modern, utility-first styling for interactive components, while Bootstrap 5 ensures a consistent grid layout.
- **Node.js & Vite**: The asset pipeline that bundles frontend code for maximum performance (HMR).

### Key Libraries
- **Sodium (Ed25519)**: A world-class library for digital signatures and encryption. Used for non-repudiation in document actions.
- **html5-qrcode**: Enables high-speed QR code scanning via mobile cameras or webcams, essential for physical document handoffs.
- **Chart.js**: Powers the real-time "Bottleneck Detector" and throughput analytics dashboards.
- **dompdf & iio/libmergepdf**: Used for generating and merging massive PDF reports (10,000+ records) efficiently using memory-safe chunking.

---

## 2. Document Lifecycle: The Digital Journey

The DTS digitizes the physical document workflow while maintaining a strict chain of custody.

| Step | Action | Responsible Code / Function | Description |
|:---|:---|:---|:---|
| **1** | **Submission** | `GuestController@store` | A guest submits a document. A **"Genesis Log"** is created with a unique tracking code. |
| **2** | **Intake** | `DocumentController@finalize` | A Records Officer reviews the document and finalizes the route (assisted by AI). The first signature is generated. |
| **3** | **Receive** | `DocumentController@scan` | A department scans the QR code to "Receive" the physical document. Status changes to **processing**. |
| **4** | **Complete** | `TaskController@complete` | A department finishes their work. The user signs the action, and the document advances to the next step. |
| **5** | **Return** | `ReturnRequestController@store` | (Optional) A department can inject a "Return Request" to send the document back for corrections. |
| **6** | **Release** | `ReleasingController@complete` | The Records Officer performs the final release. The document is marked **completed** and signed. |

---

## 3. The Trust Builder: Cryptographic Ledger

The **Trust Builder** is the core security layer that ensures no document action can be tampered with or denied.

### State Hashing (`calculateStateHash`)
Every time a log is created, the system takes a "snapshot" of the document's metadata (Title, Submitter, Department, etc.).
- **Logic**: It concatenates these fields with delimiters (`|`) and generates a **SHA-256** hash.
- **Purpose**: If anyone modifies a document's title directly in the database, the State Hash won't match the historical record, triggering an **Auto-Freeze**.

### Hash Chaining (`boot` method)
Each log entry is a "block" in a chain.
- **Logic**: A log's hash is calculated using the `previous_hash` + current metadata + `document_state_hash` + user's `signature`.
- **Independent Architecture (Micro-Sharding)**: Unlike a global blockchain, the DTS utilizes independent hash chains for each document. This provides O(log n) scaling and ensures that an integrity failure in one record does not halt the entire system.
- **Academic Support**: This approach is supported by Kim and Kim (2024), who found that navigating independent hash chains is significantly more resource-efficient and faster than monolithic blockchain ledgers for high-volume data tracking.
- **Purpose**: This creates an immutable ledger where changing one old log breaks the entire chain downstream.

### Digital Signatures (Ed25519)
The DTS uses **Ed25519** for absolute non-repudiation.
1.  **Key Storage**: Every user has a Public/Private key pair. The Private Key is encrypted using **Sodium Secretbox** with a key derived from the user's **Security PIN**.
2.  **Signing**: When a user performs an action, the system decrypts the Private Key (using the PIN) and signs a bundle containing the `Action Text` and the `State Hash`.
3.  **Departmental Identity**: While signatures are user-specific, they are cryptographically bonded to the user's unit, ensuring that "Unit Actions" are always verifiable back to a specific individual.

---

## 4. AI Route Prediction & Machine Learning

The DTS includes a smart routing assistant that suggests the most likely department for a document based on its context.

### TF-IDF Prediction Engine
The AI uses a **Weighted TF-IDF** (Term Frequency - Inverse Document Frequency) algorithm.
- **Scoring**: `Score = (Frequency of word in Title) * (Learned Weight) * (Rarity of word in system)`.
- **Why it works**: Common words like "The" are ignored. Rare, impactful words like "Salary" or "Appointment" have higher scores, correctly pointing the document to "Payroll" or "Personnel."

### Machine Learning Loop (`UpdateKeywordWeights`)
The system "learns" from human expertise.
1.  **Correction**: If a Records Officer changes an AI-suggested route, the system notices the discrepancy.
2.  **Learning Job**: A background job (`UpdateKeywordWeights`) is dispatched to analyze the document's context and increment the weights of keywords for the *actual* department chosen.
3.  **Result**: The more the system is used and "corrected," the more accurate its future suggestions become.

---

## 5. Operational Infrastructure & Analytics Logic

### Nomadic SSL Management (mDNS)
To support mobile QR scanning, the system uses **mDNS** (`.local`). This allows a phone and a server to communicate via a human-readable name without fixed IP addresses. A **Secure Proxy** (Port 3051) wraps the standard server (Port 3050) in an HTTPS "Cloak," providing the secure context required for camera access.

### Analytics: The "RAM Trap" Guard
DTS analytics dashboards use **MySQL 8.0 Window Functions** (`LAG()`, `OVER()`). 
- **The Problem**: Standard apps load 100,000 rows into RAM to calculate math, crashing the server.
- **The DTS Solution**: The database performs the math on disk before sending only the final totals to the dashboard. This ensures the system remains fast even with 1,000,000 records.

### Project Commands Matrix

| Category | Command | Purpose |
|:---|:---|:---|
| **Setup** | `composer run setup` | One-time install: installs Composer & NPM dependencies, tunes DB, generates keys, and builds assets. |
| **Development** | `composer run dev` | Starts all 5 pillars (Server, Queue, Logs, Vite, Scheduler) with hot-reloading. |
| | `composer run test` | Runs the full PHPUnit/Pest test suite. |
| | `composer run serve:dev` | Starts the PHP development server on Port 3050 (with core pinning). |
| | `composer run queue:dev` | Starts the background queue listener. |
| | `composer run vite:dev` | Starts the Vite asset bundler for frontend changes. |
| | `composer run logs:dev` | Streams real-time application logs using Laravel Pail. |
| | `composer run schedule:dev` | Runs the local task scheduler. |
| **Production** | `composer run prod` | Runs the 5 pillars in a high-performance, non-reloading state. |
| | `composer run prod:optimize` | Caches config, routes, and views for maximum production speed. |
| | `composer run prod:clear` | Clears all production caches. |
| | `composer run serve:prod` | Starts the production-optimized PHP server. |
| | `composer run queue:work-prod` | Starts a high-performance worker (512MB RAM limit). |
| | `composer run schedule:work-prod` | Runs the production-grade task scheduler. |
| **Database** | `composer run db:dev` | Resets and seeds the DB with 10,000 simulated records. |
| | `composer run db:prod` | Resets and seeds the DB with a clean production state. |
| | `composer run db:tune` | Injects optimized InnoDB settings (4GB Buffer Pool) into MySQL. |
| **Infrastructure** | `composer run proxy` | Starts the Secure HTTPS Bridge (Port 3051) for mobile testing. |
| **Security** | `php artisan dts:verify-integrity` | Performs a system-wide audit of the cryptographic ledger. |
| | `php artisan dts:rebuild-chain {id}` | Utility to repair a broken hash chain from a specific log ID. |

---

## 6. Glossary of Terms

*   **Active Guard**: The real-time integrity monitor that checks for tampering before any action.
*   **Ed25519**: A high-speed, high-security signature algorithm that is virtually unbreakable.
*   **Genesis Hash**: The very first hash in a document's chain, created during submission.
*   **HMR (Hot Module Replacement)**: A technology that updates the system's appearance in real-time during development.
*   **Non-Repudiation**: A technical guarantee that the person who signed an action cannot later deny it.
*   **SHA-256**: A mathematical algorithm that creates a unique "fingerprint" (hash) for data.
*   **Sodium**: The cryptographic library used for signatures and PIN-based encryption.
*   **TF-IDF**: A mathematical approach to understanding which words are most important in a sentence.
*   **Trust Builder**: The collective name for the system's hashing and signature infrastructure.
