# TallyMark status

## Current implementation unit

PR3 — Standalone collector merged as pull request #8; GitHub issue #7 is closed. PR4 — Visitor hashing & salts merged as pull request #10; GitHub issue #9 is closed. PR5 — Ingest pipeline merged as pull request #12; GitHub issue #11 is closed. PR6 — Sessionization & classification is blocked pending resolution of open question 8; GitHub issue: #13.

PR3 adds a standalone `public/px.php` with no Composer, Laravel, or database path. Isolated bare-server tests verify 204/CORS, no cookie, unknown-key drops, host validation, DNT, bots, body caps, IPv4/IPv6 and raw-user-agent suppression, URL sanitization, concurrent shard caps, GIF fallback, and p99 PHP timing. On merged `main`, `./scripts/tm.sh test` passed 16 tests / 183 assertions; Composer validation and the CI Compose configuration also passed before merge.

PR4 adds SHA-256 64-bit daily visitor hashes, a UTC-midnight salt rotation, one-hour salt destruction grace, a persistent `system_heartbeats` alarm for missed rotations, and the current salt to the atomically-generated collector map. It also removes request IP/User-Agent storage from fresh and existing session schemas and fixes the session driver to files. On merged `main`, `./scripts/tm.sh test` passed 22 tests / 228 assertions; Composer validation and the CI Compose configuration also passed before merge.

PR5 adds `analytics:ingest`, closed-buffer ordering, token-owned claim leases, bounded resumable line checkpoints, malformed-line tolerance, and a transient sanitized `ingest_events` stage committed before buffer deletion. On merged `main`, `./scripts/tm.sh test` passed 29 tests / 257 assertions, including exactly-once, active/reclaimed lease, budget, resume, collector-contract, and IPv4/IPv6 staging-privacy coverage; Composer validation and the CI Compose configuration also passed before merge.

PR6 cannot safely begin implementation until the specification resolves how `jaybizzle/crawler-detect` receives a User-Agent. Section 10.2 requires it at ingest, but section 8.2 prohibits raw User-Agent storage and says it exists only within the collector invocation; section 4.5 prohibits the collector from Composer autoloading. No dependency or code path has been added for PR6.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
