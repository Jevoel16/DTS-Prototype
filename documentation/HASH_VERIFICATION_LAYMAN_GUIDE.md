# Cryptographic Logs & Hashing Explained (Layman's Guide)

A simple, intuitive guide to understanding how hash chains, recalculation, and digital signatures work in the DepEd Iligan Document Tracking System (DTS) without needing a cryptography degree.

---

## The Core Concept: Notebooks, Blenders, and Wax Stamps

Imagine each document in the system carries a **notebook of receipts** and each user has a unique **wax stamp**.

A common misconception is that hashing "encodes" or "encrypts" data so it can be "decoded" later. **That is incorrect.** 

A hash function is like a **one-way blender**:
- You can put fresh fruit into a blender to make a smoothie.
- You can **never** un-blend a smoothie back into whole apples and bananas.

So how does the system verify that a log entry hasn't been tampered with if it cannot "decode" the hash?

---

## 1. What does "Recalculating a Hash" mean?

Think of a hash as a **recipe fingerprint**. 

When a log entry is created, the system takes 5 distinct ingredients sitting on the page:
1. **Document ID** (e.g., `100`)
2. **User ID** (e.g., `5`)
3. **Action** (e.g., `"Received"`)
4. **Timestamp** (e.g., `"2026-07-27 15:45:00"`)
5. **The fingerprint of the previous log entry** (the chain link)

It puts those 5 ingredients into a mathematical blender (`SHA-256`) and gets a 64-character fingerprint: `9a8f3b...`  
It writes `9a8f3b...` at the bottom of the log row as the **Block Hash**.

### How the System Verifies It Later (The Audit Check)
When an administrator runs an integrity check tomorrow, the system does **not** "decode" `9a8f3b...`. 

Instead, it does this:
1. It reads the 5 ingredients still written in the database table for that row.
2. It puts those exact ingredients into the blender again to make a **fresh smoothie**.
3. It compares the new smoothie to the stored fingerprint:
   - **Blender output today:** `9a8f3b...`
   - **Stored code in database:** `9a8f3b...`
   - **Result:** ✅ **Match!** Nobody changed the ingredients.

### What happens if an attacker tampered with the text?
If a direct database attacker secretly edits the Action from `"Received"` to `"Approved"`, but leaves the stored code `9a8f3b...` untouched:
1. The auditor script puts the ingredients (`"Approved"`, etc.) into the blender.
2. The blender produces a totally different output: `3x7k1m...`
3. The script compares:
   - **Blender output today:** `3x7k1m...`
   - **Stored code in database:** `9a8f3b...`
4. **Result:** ❌ **MISMATCH!** The system immediately flags that the row was altered.

---

## 2. What does "Signed over Action | StateHash" mean?

A hash only proves *what* data was written. A **digital signature** proves *who* authorized it and prevents people from putting words in your mouth.

When a staff member (e.g., Alice) processes a document:
1. The system glues two pieces of information together into one exact sentence:  
   `"Received | StateHash123"`
2. Alice enters her secret 6-digit PIN. This unlocks her personal **digital wax stamp** (her Ed25519 private key).
3. The system stamps her digital signature onto that exact sentence.

### How Anyone Verifies the Signature Later
Alice’s public stamp pattern (her public key) is stored in the database for everyone to see.

To verify her signature, the system takes three things:
1. Alice's public key (the stamp pattern on the wall)
2. The claimed sentence: `"Received | StateHash123"`
3. The digital signature attached to the log

The cryptographic math asks a simple True/False question:  
> *"Did the holder of Alice's private key sign this EXACT sentence?"*

- If an attacker changed the action in the database to `"Approved"`, the verification algorithm checks Alice's signature against `"Approved | StateHash123"`.
- The math returns **FALSE**, because Alice's key signed `"Received..."`, not `"Approved..."`.

---

## Quick Reference Summary

| Concept | Real-World Analogy | Security Guarantee |
| :--- | :--- | :--- |
| **SHA-256 Hash** | A one-way blender that turns data into a fixed 64-character recipe fingerprint. | **Integrity:** Proves data has not been modified since the fingerprint was generated. |
| **Hash Recalculation** | Re-blending the raw table ingredients today and comparing the result to yesterday's fingerprint. | **Tamper Detection:** Catches direct SQL modifications to text, dates, or user IDs. |
| **Ed25519 Digital Signature** | Pressing a personal wax stamp onto a specific sentence using a PIN-unlocked key. | **Authenticity & Non-Repudiation:** Proves *which specific user* approved the document state. |
| **Active Guard** | Checking fingerprints *before* allowing the next user to receive or work on a document. | **Real-Time Protection:** Immediately freezes tampered documents before bad data spreads. |
