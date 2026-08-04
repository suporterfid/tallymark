# Backlog and open questions

No open questions are currently blocking PR9. The owner authorized the sustainable choice: PR5 commits a sanitized, transient `EventLine` to an `ingest_events` staging store linked to `ingest_batches`, then deletes the buffer only after that transaction commits. PR6/PR7 consume and remove staged events during classification and aggregation; the store has no IP or raw User-Agent fields and is not an export surface.

The owner chose a UTC-midnight rotation boundary for PR4. A missed rotation writes and retains an `alarm` state for `analytics:maintenance` in `system_heartbeats`, which is the durable operator-facing operational state for the future authenticated health endpoint.

Until PR4 introduces the salts table and rotation, the PR2 site map omits the `salt` key by owner decision; section 7.3 records the sequencing rule.

## Resolved questions 1–7

1. Keep the file buffer. PR15's load fixture must measure and record the buffer-versus-direct-UPSERT comparison before any reconsideration.
2. Keep exact daily visitor counting through `daily_visitors`, with the specified retention cap. PR8 now materializes the exact per-site daily set and writes that count into daily totals.
3. Sampling is opt-in only. Any sampled figure must be labelled everywhere it appears.
4. A first-party collector proxy is post-v0; the collector contract remains stable for a future proxy.
5. Country resolution defaults to an edge header, falling back to `unknown`; no geo database is vendored. An operator-supplied database remains an opt-in future path.
6. GrandpaSSOn inbound mode remains disabled until the broker owns and ships `analytics:read`, `analytics:write`, and `analytics:callback`; [GrandpaSSOn #115](https://github.com/suporterfid/grandpasson/issues/115) tracks that prerequisite, and PR12 must use its fake-backed, flags-off mode until then.
7. Keep four buffer shards by default. Document the inode arithmetic and add orphan-accumulation warning coverage with the PR15 load/capacity work.

## Resolved question 8 — PR6 bot-classification input

Section 10.2 requires `jaybizzle/crawler-detect` to classify bots at ingest. Section 8.2 prohibits the raw User-Agent from being written to the buffer, database, logs, or cache and says it exists only inside one collector invocation. Section 4.5 prohibits `public/px.php` from Composer autoloading, so the collector cannot load that dependency either. The current PR3 collector deliberately discards the User-Agent after its cheap prefilter, and PR5's staged `EventLine` contains no User-Agent.

Owner decision: v0 uses a small zero-dependency collector classifier and writes only derived bot/device/browser/OS fields. The specification and issue #13 record this amendment. Retaining or buffering the raw User-Agent remains prohibited.

## Follow-up — Composer security advisory feed

During PR6, `composer audit --locked --no-interaction` could not query Packagist because the advisory endpoint timed out. Composer had reported two advisories affecting one existing package during installation, but no advisory detail was retrieved or treated as verified. Re-run the network-backed audit during PR14's release work and record the actual package/advisory result before shipping a release.

## Open question 9 — PR9 goals and real-time screen sequencing

Section 19 and PR9 require Goals and a real-time-ish “last 30 minutes” screen, but §20 schedules the Goals data model and conversion metrics for PR11. The only retained reporting data available in PR9 is hourly aggregation, which cannot truthfully represent a 30-minute window. Choose one: move the required Goal model/metrics and a sufficiently granular real-time aggregate into PR9; defer the two screens to PR11 and amend PR9’s deliverable; or explicitly define a labelled hourly approximation. PR9 remains unmerged until this is resolved.
