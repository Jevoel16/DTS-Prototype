# Hash Chain Functionality (The Trust Builder)

This document visualizes the cryptographic logic used by the **Document Tracking System (DTS)** to ensure document immutability, non-repudiation, and chronological integrity.

## 📊 Logic Flow Diagram

```mermaid
graph TD
    subgraph State_Capture ["1. State Hashing"]
        Metadata["Document Metadata<br/>(Title, Submitter, Purpose, Route)"] -->|SHA-256| StateHash["document_state_hash"]
    end

    subgraph Signing ["2. Digital Signature"]
        Action["Action Text"] --> SignEngine
        StateHash --> SignEngine{Ed25519 Sign}
        PrivateKey["User Private Key<br/>(Decrypted via PIN)"] --> SignEngine
        SignEngine --> Signature["Digital Signature"]
    end

    subgraph Block_N ["Log Entry N"]
        PrevHash["previous_hash"]
        LogMeta["Metadata<br/>(User, Action, Timestamp)"]
        StateHash_N["document_state_hash"]
        Sig_N["signature"]
        
        PrevHash --- BundleN["Data Bundle"]
        LogMeta --- BundleN
        StateHash_N --- BundleN
        Sig_N --- BundleN
        
        BundleN -->|SHA-256| CurrentHash["Current Hash"]
    end

    subgraph Block_N_plus_1 ["Log Entry N+1"]
        LogMeta2["Metadata"]
        StateHash2["document_state_hash"]
        Sig2["signature"]
        
        CurrentHash -->|Linked As| PrevHash2["previous_hash"]
        PrevHash2 --- Bundle2["Data Bundle"]
        LogMeta2 --- Bundle2
        StateHash2 --- Bundle2
        Sig2 --- Bundle2
        
        Bundle2 -->|SHA-256| NextHash["Next Hash"]
    end

    Genesis((Genesis Hash)) -->|Initial Link| Block_N
    
    style Genesis fill:#f9f,stroke:#333,stroke-width:2px
    style StateHash fill:#bbf,stroke:#333,stroke-width:2px
    style Signature fill:#bfb,stroke:#333,stroke-width:2px
    style CurrentHash fill:#f96,stroke:#333,stroke-width:4px
```

## 🔐 Core Security Properties

1.  **State Integrity (The Snapshot):** Every log captures a `document_state_hash`. If a document's title or submitter info is modified directly in the database, the live state hash will no longer match the historical record stored in the last log. This triggers the **Active Guard** (via `DocumentPolicy`) and auto-freezes the document.

2.  **Non-Repudiation (The Bond):** The Ed25519 digital `signature` is mathematically bonded to both the `Action Text` and the `document_state_hash`. This ensures that a user cannot later claim they signed a different version of the document or authorized a different action.

3.  **Chain Continuity (The Link):** Each log entry stores the `previous_hash` of the preceding log. Because the `Current Hash` is a SHA-256 result of all current data (including the `previous_hash`), any alteration to a historical entry—even a single millisecond in a timestamp—invalidates every subsequent block in that document's specific chain.

4.  **Micro-Sharding (The Scale):** Unlike a global blockchain, the DTS utilizes independent hash chains for every document. This ensures that an integrity failure in one record does not halt the entire system and maintains $O(\log n)$ performance during verification.

---
*Refer to `app/Models/DocumentLog.php` for the implementation of this logic.*
