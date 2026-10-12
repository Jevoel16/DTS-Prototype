# DTS Quantum Safety & Post-Quantum Strategy

## Summary
A technical assessment of the Document Tracking System's resilience against future quantum-based attacks. This document explains the vulnerabilities of classical "Asymmetric" cryptography, the relative safety of "Symmetric" hashing, and the project's roadmap for transitioning to **Post-Quantum Cryptography (PQC)**.

## Table of Contents
1. [The Quantum Threat Model](#1-the-quantum-threat-model)
2. [Vulnerability Analysis: Ed25519 vs. Shor's Algorithm](#2-vulnerability-analysis-ed25519-vs-shors-algorithm)
3. [Resilience Analysis: SHA-256 vs. Grover's Algorithm](#3-resilience-analysis-sha-256-vs-grovers-algorithm)
4. [The "Gaslighting" Attack Scenario](#4-the-gaslighting-attack-scenario)
5. [Mitigation & Post-Quantum Roadmap](#5-mitigation--post-quantum-roadmap)
6. [Glossary of Terms](#6-glossary-of-terms)

---

## 1. The Quantum Threat Model

The DTS **"Trust Builder"** relies on two primary cryptographic pillars to ensure security and non-repudiation:
1.  **SHA-256 (Hashing)**: Creates unique digital "Fingerprints" that connect the immutable ledger blocks.
2.  **Ed25519 (Signatures)**: Proves that a specific user performed a specific action using their private key and PIN.

In a future where powerful **Quantum Computers** exist, these pillars face two distinct threats: **Shor's Algorithm** and **Grover's Algorithm**.

---

## 2. Vulnerability Analysis: Ed25519 vs. Shor's Algorithm

The most critical vulnerability in the current system is the **Ed25519** signature layer. Ed25519 is a high-security "Padlock" based on complex elliptic curve math.

### The "Lock Picker" (Shor's Algorithm)
- **Classical World**: Even the world's most powerful supercomputers would take billions of years to guess your private key.
- **Quantum Future**: **Shor's Algorithm** acts as a specialized "Lock Picker." It doesn't guess your key; it solves the mathematical puzzle instantly.
- **Impact**: A quantum computer could analyze your **Public Key** and instantly calculate your **Private Key**, allowing an attacker to forge your signature on any document.

---

## 3. Resilience Analysis: SHA-256 vs. Grover's Algorithm

The **SHA-256** "Fingerprints" used by the Trust Builder are much more resilient to quantum attacks.

### The "Library Searcher" (Grover's Algorithm)
- **Quantum Attack**: **Grover's Algorithm** is like a very fast librarian searching for a specific book. It doesn't "break" the math; it just makes searching faster.
- **Impact**: While Grover's Algorithm makes the search faster, 256-bit security is so strong that even a quantum-speed search would take longer than the age of the universe.
- **Result**: The **Immutability** of the ledger (the fact that data cannot be changed without breaking the hash chain) is likely safe for decades, even after quantum computers arrive.

---

## 4. The "Gaslighting" Attack Scenario

Without quantum-resistant signatures, a quantum attacker could perform a "Gaslighting" attack, effectively rewriting history.

### Attack Steps:
1.  **Modify Data**: The attacker changes a document's details or a staff member's action in the database.
2.  **Forge Signature**: Using a quantum computer, they forge the staff member's signature so it looks perfectly valid.
3.  **Repair the Chain**: They update the SHA-256 "Fingerprints" (hashes) so the whole chain looks connected and healthy.
4.  **The Result**: The `dts:verify-integrity` command will report **"100% Healthy"**. The system "believes" the lie because the math matches the modified data perfectly.

---

## 5. Mitigation & Post-Quantum Roadmap

To achieve true "Quantum-Safe" status, DTS is preparing a roadmap to move beyond today's math to **Lattice-Based Cryptography**.

### A. Transition to Post-Quantum Signatures (PQC)
DTS aims to replace Ed25519 with new, NIST-approved algorithms like **ML-DSA (Dilithium)**. These locks use "Three-Dimensional Grid" puzzles that are currently impossible for both classical and quantum computers to solve.

### B. Hybrid Signatures (The "Two Lock" Strategy)
During the transition, we will use **Hybrid Signatures**. Every action will be signed with **two different locks**:
1.  One **Ed25519** lock (today's standard).
2.  One **ML-DSA** lock (the quantum-safe standard).
An attacker would have to break **both** systems simultaneously to forge your identity.

### C. External Anchoring
To prevent a "Total History Rewrite," we can "Anchor" the system to an external, write-only source (such as a Public Blockchain or a Remote Log Server). This is like taking a photo of a document and putting it in a public newspaper—an attacker might change the original, but they can't change all the copies in the world.

---

## 6. Glossary of Terms

*   **Authenticity**: Mathematical proof that data is genuine and comes from the stated source.
*   **CRQC**: "Cryptographically Relevant Quantum Computer." A future computer powerful enough to break today's security.
*   **Ed25519**: Today's standard "Lock" used for digital signatures.
*   **External Anchoring**: Storing a backup "Snapshot" of your data in a safe place outside your own system.
*   **Grover's Algorithm**: A quantum program that speeds up data searching.
*   **Hybrid Signatures**: Using two different types of security locks for double protection.
*   **Immutability**: The quality of being impossible to change without detection.
*   **Lattice-Based Cryptography**: A new type of math puzzle that quantum computers cannot solve easily.
*   **ML-DSA (Dilithium)**: A specific "Quantum-Safe" lock approved by the U.S. government (NIST).
*   **Non-Repudiation**: A technical guarantee that you cannot deny an action you've taken.
*   **Post-Quantum Cryptography (PQC)**: Security methods designed to be safe against quantum computers.
*   **Shor's Algorithm**: A quantum program that can instantly "pick the lock" of today's signature security.
*   **Symmetric Hashing (SHA-256)**: A way to create a unique fingerprint for data.
*   **Trust Builder**: The collective name for the system's security and cryptographic layers.
