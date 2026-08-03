# Backlog and open questions

No open questions are currently blocking PR5. The owner authorized the sustainable choice: PR5 commits a sanitized, transient `EventLine` to an `ingest_events` staging store linked to `ingest_batches`, then deletes the buffer only after that transaction commits. PR6/PR7 consume and remove staged events during classification and aggregation; the store has no IP or raw User-Agent fields and is not an export surface.

The owner chose a UTC-midnight rotation boundary for PR4. A missed rotation writes and retains an `alarm` state for `analytics:maintenance` in `system_heartbeats`, which is the durable operator-facing operational state for the future authenticated health endpoint.

Until PR4 introduces the salts table and rotation, the PR2 site map omits the `salt` key by owner decision; section 7.3 records the sequencing rule.

## Open question 8 — PR6 bot-classification input

Section 10.2 requires `jaybizzle/crawler-detect` to classify bots at ingest. Section 8.2 prohibits the raw User-Agent from being written to the buffer, database, logs, or cache and says it exists only inside one collector invocation. Section 4.5 prohibits `public/px.php` from Composer autoloading, so the collector cannot load that dependency either. The current PR3 collector deliberately discards the User-Agent after its cheap prefilter, and PR5's staged `EventLine` contains no User-Agent.

Decision required before PR6 can meet its Definition of Done without weakening a hard constraint: amend the bot-classification requirement to a collector-safe, derived-only signal, or specify another zero-dependency collector-compatible mechanism for obtaining the crawler-detect result. Retaining or buffering the raw User-Agent is not an acceptable default.
