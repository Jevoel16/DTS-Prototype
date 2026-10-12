# Cryptographic Logs in the DTS

How the DepEd Iligan Document Tracking System (DTS) seals workflow history, why that design is proportionate for administrative documents, and how it differs from “just encryption.”

Implementation reference (active codebase): `src/Core/IntegrityManager.php`, `src/Policies/DocumentPolicy.php`, `src/Services/DocumentWorkflowService.php`.

Related historical diagrams (Laravel-era reference): [laravel-era/HASH_CHAIN_VISUALIZATION.md](laravel-era/HASH_CHAIN_VISUALIZATION.md).

---

## 1. Low level — How cryptographic logs work

This is not ordinary application logging (“user X clicked Y”). Each document maintains its **own** hash chain in `document_logs`. Chains are independent on purpose: a integrity failure on one folder freezes that document’s workflow, not the entire division.

Whenever a custody-relevant action occurs (Submitted, route finalized, Received, Processing Complete, Released, System Auto-Freeze), `IntegrityManager::createLog` builds a sealed log entry in four steps:

1. **State hash** — SHA-256 over critical document fields (tracking code, title, guest info, district, department, purpose, finalized route). This fingerprints *what* the document claimed to be at that moment.
2. **Signature** — the actor’s Ed25519 private key (unlocked with their PIN) signs `action + stateHash`. This attests *who* accepted that state. System-initiated actions use a marked system signature.
3. **Chain link** — the new block hash covers document id, user, action, timestamp, **previous hash**, state hash, and signature. Each log is sealed to the prior one (`genesis_hash` for the first entry).
4. **Snapshot** — the document payload is stored on the log so an admin can Auto-Resolve back to a known-good sealed state.

Before staff or officers can mutate a document, `DocumentPolicy::process` (and related manage checks) run **Active Guard**: recompute the live state hash and compare it to the last sealed `document_state_hash`. On mismatch, the system auto-freezes the document. Verification is operational at the next handoff, not deferred to a nightly audit.

### Lifecycle ↔ cryptographic seal

| Step | Typical action | What gets sealed |
| :--- | :--- | :--- |
| Submission | Guest submits | Genesis log + initial state hash |
| Intake | Officer finalizes route | Signed “routing finalized” + updated state |
| Receive | QR scan at department | Signed receive on current state |
| Complete | Staff finishes step | Signed completion; chain advances |
| Release | Officer releases | Signed completion of custody |
| Tamper detected | Active Guard / integrity job | System Auto-Freeze (halt workflow) |

### Implementer map

| Component | Role |
| :--- | :--- |
| `IntegrityManager::calculateStateHash` | Fingerprint of critical document fields |
| `IntegrityManager::signAction` | Ed25519 (or system) attestation |
| `IntegrityManager::createLog` | Append hash-chained, signed log + snapshot |
| `IntegrityManager::verifyCurrentState` | Live row vs last sealed state hash |
| `IntegrityManager::autoFreeze` | Halt workflow on mismatch |
| `DocumentPolicy::process` / `manage` | Active Guard before mutating actions |
| `users.private_key` + PIN | Encrypted key wrap (confidentiality only) |

---

## 2. High level — Why this exists (and is not overengineering)

A database alone is **mutable truth**. An admin, a compromised account, or direct SQL can rewrite `documents` or delete `document_logs`. Paper seals do not silently rewrite themselves; MySQL can. Cryptographic logs make digital custody behave like paper seals when the storage layer is hostile or fallible.

### The birth-certificate analogy

A birth certificate is not “proof a person is real” in one magical form. It is the tip of a **chain of attestations**:

- hospital / midwife attestation
- civil registry intake
- seals, serial numbers, wet signatures
- later authenticated copies, PSA verification, passports grounded on that record

Each step means: *this claim was accepted by this authority, at this time, under this identity*. Lose the chain and you lose the ability to prove the claim was not rewritten later.

DepEd documents (appointments, payroll packets, school credentials, and similar administrative instruments) work the same way. Their value is not a status enum (`pending` → `processing` → `completed`). Their value is a **verifiable sequence of custody steps**. Hash chaining makes that sequence tamper-evident; signatures make steps attributable; Active Guard makes verification part of daily operations.

### Proportionate design vs overengineering

For an internal Kanban board, append-only tables and ACLs may be enough. For documents that later justify pay, appointments, or legal standing, the threat model is “someone with DB access can rewrite history.” Silent rewrite is catastrophic.

Overengineering would look like public L1 blockchains, zero-knowledge proofs for “folder is in Accounting,” or vanity PKI. What DTS does maps paper rituals to digital seals:

| Paper world | DTS crypto |
| :--- | :--- |
| Seal / wet signature | Ed25519 + PIN |
| Serial + prior stamps | `previous_hash` chain |
| “Does this copy match the registry?” | state hash vs live row |
| Impound forged packet | auto-freeze |
| Reissue from registry copy | snapshot Auto-Resolve |

The PIN is the digital wet-signature ceremony: the actor attests that a receive/complete happened while the folder matched the sealed state. Without that ceremony, the log is only a blog post the server wrote about itself.

Cryptography here is not decoration. It encodes **institutional memory** so a digital folder has the same property as a sealed civil record: later parties can detect whether the story changed.

---

## 3. Key takeaways

### Cryptography vs encryption

**Encryption is a subset of cryptography**, not a synonym. DTS uses multiple cryptographic services; only key wrapping is encryption.

| Goal | Primitive in DTS | Encryption? |
| :--- | :--- | :--- |
| Keep the signing key secret at rest | AES-256-CBC + PIN → Argon2id (or legacy SHA-256) key wrap | **Yes** — confidentiality only |
| Detect altered document rows / broken log sequence | SHA-256 state hash + hash chain (`previous_hash`) | **No** — integrity |
| Prove who attested an action | Ed25519 detached signature over `action \| stateHash` | **No** — authenticity / non-repudiation |

- Encryption answers: *can someone recover the private key from a DB dump without the PIN?*
- Hashing answers: *did anyone change sealed fields or break the log sequence?*
- Signatures answer: *did this key holder authorize this step on that state?*

The logs themselves are **not encrypted**. Auditors must be able to read them. Encrypting logs would hide content; hashing and signing make tampering *evident*. Different goals.

A lone SHA-256 of a row is only a strong checksum. The protocol becomes cryptographic custody when it also:

1. binds each step to prior steps (`previous_hash`)
2. binds each step to document meaning (`document_state_hash`)
3. binds each step to an identity (Ed25519 over action+state)
4. verifies that binding before further state changes (Active Guard)

Those four bindings deliver integrity, authenticity, and practical non-repudiation. Encryption never enters those steps — it only protects the pen (private key) so it is not left on the desk.

### Bottom line

- This is **true cryptography** (hashing + digital signatures + a verification protocol), with encryption as a supporting key-locker detail.
- It is **not blockchain cosplay**: per-document chains, no public ledger theater.
- It is **not overengineering** when the requirement is civil/administrative chain of custody under a mutable database.
- If you only need a ticket tracker, strip the seals. If you need birth-certificate-grade provenance for folders, seals and signatures are the product — not optional garnish.
