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

## Resolved follow-up — Composer security advisory feed

The network-backed audit identified and remediated two advisories in transitive `guzzlehttp/guzzle` 7.15.1: PKSA-gcrk-3vtt-1r14 / CVE-2026-69246 (high) and PKSA-cnw1-2ytm-cgr8 / CVE-2026-69245 (medium). The lockfile now pins 7.15.2, which is outside both affected ranges. The exact evidence and the fail-closed release-gate policy are recorded in `docs/security/composer-advisories.md`.

## Open question 9 — PR9 goals and real-time screen sequencing

Resolved by owner authorization: move the minimum Goal model/conversion reporting and a five-minute real-time aggregate into PR9. PR11 retains shared dashboards and any remaining goal management features. The five-minute data powers the labelled “last 30 minutes” report without fabricating an hourly window.
