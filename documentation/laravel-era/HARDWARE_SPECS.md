# DTS Hardware Requirements & Performance Specifications

## Summary
A technical guide detailing the hardware infrastructure required to run the Document Tracking System (DTS) effectively. This document focuses on **high-concurrency** scenarios, ensuring the system remains responsive under heavy load (1,000,000+ records and hundreds of simultaneous users). It provides both minimum and recommended specifications based on the system's memory-intensive architecture.

## Table of Contents
1. [Standard Hardware Specifications](#1-standard-hardware-specifications)
2. [Technical Rationale: Why these specs?](#2-technical-rationale-why-these-specs)
3. [Memory Strategy: InnoDB Buffer Pool](#3-memory-strategy-innodb-buffer-pool)
4. [CPU Strategy: Core Pinning & Job Management](#4-cpu-strategy-core-pinning--job-management)
5. [Storage Forecasting for 1M+ Records](#5-storage-forecasting-for-1m-records)
6. [Glossary of Terms](#6-glossary-of-terms)

---

## 1. Standard Hardware Specifications

These specifications are designed for high-concurrency environments, where multiple departments are processing documents simultaneously.

| Component | Minimum (High-Concurrency Ready) | Recommended (Enterprise Production) |
|:---|:---|:---|
| **CPU** | 8 Cores (3.0GHz+) | 16+ Cores (3.5GHz+) |
| **RAM** | 16 GB | 64 GB+ (ECC Recommended) |
| **Storage** | 100 GB NVMe SSD (High IOPS) | 500 GB+ NVMe SSD (RAID 10) |
| **Network** | 1 Gbps (Low Latency) | 10 Gbps (Fiber Optic Backbone) |

---

## 2. Technical Rationale: Why these specs?

The DTS is not a typical web application. It performs complex cryptographic math and real-time database lookups that require significant resources.

1.  **High IOPS (Storage)**: During a security audit (`dts:verify-integrity`), the system must read and re-verify millions of SHA-256 hashes. Traditional hard drives (SATA) will become a severe bottleneck. NVMe SSDs are required for their high "Input/Output Operations Per Second."
2.  **Clock Speed (CPU)**: Ed25519 digital signatures and SHA-256 hashing are CPU-intensive. Faster clock speeds directly translate to faster document intake and release times.
3.  **Low Latency (Network)**: While the QR scanner (`html5-qrcode`) is client-side, the **"Receive"** action requires an instant database lookup and state update. High network latency or slow DB response times will cause a noticeable delay ("lag") between the physical scan and the digital confirmation, frustrating staff in high-volume queues.
4.  **Verification Scalability (Architecture)**: The system utilizes **Independent Hash Chains** (Micro-Sharding) for each document. Unlike traditional blockchains that require a global ledger check, DTS only verifies the specific chain for a single document. This reduces the **CPU and RAM overhead** for verification by orders of magnitude, allowing for sub-second audit times even as the total database grows to millions of records (Kim & Kim, 2024).

---

## 3. Memory Strategy: InnoDB Buffer Pool

The system's speed depends on keeping the database "Map" (the index) in fast memory (RAM).

### How to Allocate RAM:
1.  **Dynamic Tuning**: Run `composer run db:tune` to instantly inject a 4GB buffer pool setting into the active MySQL instance.
2.  **Permanent Configuration**: Locate your MySQL configuration file:
    -   **Linux**: `/etc/mysql/my.cnf` or `/etc/my.cnf`
    -   **Windows**: `C:\ProgramData\MySQL\MySQL Server 8.0\my.ini`.
3.  **Apply Changes**: Add or modify the following under the `[mysqld]` section:
    ```ini
    [mysqld]
    innodb_buffer_pool_size = 32G  # Set to at least 50% of your total RAM
    innodb_log_file_size = 1G
    ```
- **Why?**: This ensures that looking up 1 document among 1,000,000 takes less than **0.001 seconds** because the system never has to check the slow hard drive to find where the data is located.

---

## 4. CPU Strategy: Core Pinning & Job Management

To prevent heavy background tasks (like generating a 500-page PDF report or AI learning) from slowing down the main website, DTS uses a **Split-Thread Philosophy**. By default, the system is configured to reserve half of the available CPU threads for user-facing interactions and the other half for "heavy lifting."

### Default System Allocation (8-Core Baseline):
The system uses the following default mapping in `composer.json`:
-   **Web Server (`Cores 0-3`)**: Reserved for handling HTTP requests. This ensures that even if the system is under heavy background load, the website remains responsive.
-   **Heavy Lifters (`Core 4`)**: Dedicated to the Queue Worker (PDF generation, AI training).
-   **System Diagnostics (`Core 5`)**: Dedicated to real-time log streaming.
-   **Asset Pipeline (`Cores 6-7`)**: Dedicated to Vite for instant CSS/JS updates.
-   **Task Scheduler (`Core 8`)**: Dedicated to periodic maintenance like security audits.

### How to Edit Core Pinning (Affinity):

#### Linux (`taskset`)
Core pinning is managed via the `taskset` utility within the `scripts` section of `composer.json`.
1.  **Open `composer.json`**.
2.  **Modify the `-c` flag**: Change the range (e.g., `taskset -c 0-7`) to match your server's core count. 
    -   *Recommendation*: Allocate the first 50% of your cores to `serve:dev` and the remaining 50% to the other background processes.

#### Windows (`start /affinity`)
Windows does not use `taskset`. To achieve the same result via the command line, you must use the `start /affinity` command with a **Hexadecimal Mask**.
-   **The Mask**: Each bit represents a core. `1` (hex) = Core 0, `F` = Cores 0-3, `FF` = Cores 0-7.
-   **Example**: To pin the server to the first 4 cores (similar to Linux `0-3`):
    ```cmd
    start /affinity F php artisan serve --port 3050
    ```
-   **Manual Method**: Right-click the process in **Task Manager** > **Go to details** > Right-click the `.exe` > **Set affinity**.

**Result**: This isolation ensures that a massive 10,000-record report generation task on Core 4 will never cause a "hang" or slowdown for a Records Officer performing an intake on Cores 0-3.

## 5. Storage Forecasting for 1M+ Records

The "Trust Builder" cryptographic ledger grows over time. Below is the projected storage footprint for 1 million documents:

1.  **Core Document Metadata**: ~2.0 GB
2.  **The Ledger (5,000,000+ Logs & Hashes)**: ~5.0 GB (The heaviest component).
3.  **Analytics History**: ~1.0 GB.
4.  **PDF/CSV Report Cache**: ~15.0 GB (Temporary files). 
    - **Optimization**: The system includes an automated **Report Pruning Job**. Exported reports are automatically deleted from the server 60 minutes after generation to prevent storage exhaustion, as there is no historical download feature for security reasons.
5.  **Total Projected Database Size**: **~25 GB to 35 GB**.

---

## 6. Glossary of Terms

*   **ECC RAM**: "Error Correction Code" memory. It detects and fixes data corruption automatically, which is vital for high-security databases.
*   **High Concurrency**: A situation where many users are using the system at the exact same time.
*   **InnoDB Buffer Pool**: A slice of RAM used by the database to keep data "instantly ready" instead of loading it from the hard drive.
*   **IOPS**: "Input/Output Operations Per Second." A measure of how many separate "reads" or "writes" a hard drive can do in one second.
*   **NVMe SSD**: The newest and fastest type of hard drive that connects directly to the server's brain.
*   **RAID 10**: A way of combining multiple hard drives for both extreme speed and data safety.
*   **Taskset**: The technical command used to perform "CPU Core Pinning."
