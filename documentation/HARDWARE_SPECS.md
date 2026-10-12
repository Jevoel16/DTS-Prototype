# DTS Hardware Requirements & MySQL Tuning

## Summary
Server sizing for the PHP DTS at high load (1,000,000+ documents, hundreds of simultaneous users), and how to give MySQL enough memory. Adapted from the Laravel-era guide; the parts that only applied to Laravel (Vite, Artisan, CPU pinning of dev tools, report pruning) were removed.

## Table of Contents
1. [Hardware Specifications](#1-hardware-specifications)
2. [Why These Specs?](#2-why-these-specs)
3. [Memory: InnoDB Buffer Pool](#3-memory-innodb-buffer-pool)
4. [Processes on the Server](#4-processes-on-the-server)
5. [Storage Forecast for 1M+ Documents](#5-storage-forecast-for-1m-documents)
6. [Glossary](#6-glossary)

---

## 1. Hardware Specifications

| Component | Minimum (high concurrency) | Recommended (production) |
|:---|:---|:---|
| **CPU** | 8 cores (3.0 GHz+) | 16+ cores (3.5 GHz+) |
| **RAM** | 16 GB | 64 GB+ (ECC recommended) |
| **Storage** | 100 GB NVMe SSD | 500 GB+ NVMe SSD (RAID 10) |
| **Network** | 1 Gbps LAN | 10 Gbps backbone |

A small office pilot (a few thousand documents, ~20 users) runs comfortably on a 4-core / 8 GB machine.

---

## 2. Why These Specs?

1. **Storage IOPS:** **Run Verification** (`IntegrityCheckJob`) re-reads and re-hashes every log entry in chunks. NVMe SSDs keep a full audit of millions of rows from becoming disk-bound.
2. **CPU clock speed:** Every signed action does an Argon2id key derivation (deliberately slow, to protect the PIN), an Ed25519 signature and several SHA-256 hashes. Faster cores mean faster intake, receive and release.
3. **Low latency:** QR scanning happens in the browser (`html5-qrcode`), but **Receive** needs a database round trip. Slow networks or a slow database show up as lag between the scan and the confirmation.
4. **Per-document hash chains:** Each document has its own chain (micro-sharding), so checking one document only reads that document's log entries, no matter how large the database grows (Kim & Kim, 2024).

---

## 3. Memory: InnoDB Buffer Pool

Keeping MySQL's indexes in RAM is what keeps lookups fast at 1M+ rows.

1. **Quick (temporary) tuning:** `php scripts/tune-database.php` sets `innodb_buffer_pool_size` to 4 GB on the running server (`SET GLOBAL`, needs a MySQL user with the privilege). It is lost when MySQL restarts.
2. **Permanent setting:** edit the MySQL config file:
   - **Linux:** `/etc/mysql/my.cnf` or `/etc/mysql/mysql.conf.d/mysqld.cnf`
   - **Windows:** `C:\ProgramData\MySQL\MySQL Server 8.0\my.ini`

   ```ini
   [mysqld]
   innodb_buffer_pool_size = 32G   # about 50-70% of RAM on a dedicated DB server
   innodb_log_file_size    = 1G
   ```
   Restart MySQL afterwards.
3. **Existing databases:** run `composer run migrate` so the indexes the app relies on exist.

---

## 4. Processes on the Server

| Process | What it does | Started by |
|:---|:---|:---|
| NGINX | HTTPS, static files (gzip + 30-day browser cache), forwards PHP requests | `composer run dev` / `prod` |
| PHP-FPM (Linux) or 4× `php-cgi` (Windows) | Runs the app for each request | `composer run dev` / `prod` |
| `console.php` worker | Runs queued jobs (reports, backups, verification) in up to 2 child processes, plus the scheduler | `composer run dev` / `prod` / `worker` |
| MySQL 8 | Database | OS service |

**Sizing tips**
- On Linux, raise PHP-FPM's `pm.max_children` (in `/etc/php/X.Y/fpm/pool.d/www.conf`) for more simultaneous users; each child needs roughly 20-40 MB.
- On Windows, the number of `php-cgi` processes is `DTS_WIN_FASTCGI_POOL` in [scripts/server-lib.php](../scripts/server-lib.php) (default 4). Windows/php-cgi suits pilots and development; use Linux for production.
- Background jobs run in separate processes, so a large report or verification does not block web requests. On Linux you can optionally pin them to specific cores with `taskset` (e.g. `taskset -c 4-7 php console.php`).

---

## 5. Storage Forecast for 1M+ Documents

1. **Document rows:** ~2 GB
2. **The ledger** (`document_logs`, ~5M signed entries with snapshots): ~5 GB, the largest part
3. **Analytics** (`daily_department_metrics`, `database_metrics`, rolled up hourly after 24 h): ~1 GB
4. **Reports:** none on disk. XLSX reports are streamed straight to the browser when downloaded; only the job record and filters are stored, which is how **Past Reports** can be re-downloaded.
5. **Backups:** each zipped dump is a fraction of the database size; keep a few and download older ones off-site.

**Total database:** about **10-15 GB**, plus backups.

---

## 6. Glossary

- **ECC RAM:** Memory that detects and corrects bit errors; recommended for database servers.
- **High concurrency:** Many users acting at the same moment.
- **InnoDB buffer pool:** RAM MySQL uses to keep data and indexes ready instead of reading the disk.
- **IOPS:** Input/output operations per second; how many separate reads/writes a disk can do.
- **NVMe SSD:** The fastest common type of SSD, attached directly over PCIe.
- **PHP-FPM / php-cgi:** The processes that run PHP code for NGINX.
- **RAID 10:** Several disks combined for both speed and redundancy.
