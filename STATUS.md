# TallyMark status

## Current implementation unit

PR3 — Standalone collector merged as pull request #8; GitHub issue #7 is closed. PR4 — Visitor hashing & salts merged as pull request #10; GitHub issue #9 is closed. PR5 — Ingest pipeline merged as pull request #12; GitHub issue #11 is closed. PR6 — Sessionization & classification merged as pull request #14; GitHub issue #13 is closed.

PR3 adds a standalone `public/px.php` with no Composer, Laravel, or database path. Isolated bare-server tests verify 204/CORS, no cookie, unknown-key drops, host validation, DNT, bots, body caps, IPv4/IPv6 and raw-user-agent suppression, URL sanitization, concurrent shard caps, GIF fallback, and p99 PHP timing. On merged `main`, `./scripts/tm.sh test` passed 16 tests / 183 assertions; Composer validation and the CI Compose configuration also passed before merge.

PR4 adds SHA-256 64-bit daily visitor hashes, a UTC-midnight salt rotation, one-hour salt destruction grace, a persistent `system_heartbeats` alarm for missed rotations, and the current salt to the atomically-generated collector map. It also removes request IP/User-Agent storage from fresh and existing session schemas and fixes the session driver to files. On merged `main`, `./scripts/tm.sh test` passed 22 tests / 228 assertions; Composer validation and the CI Compose configuration also passed before merge.

PR5 adds `analytics:ingest`, closed-buffer ordering, token-owned claim leases, bounded resumable line checkpoints, malformed-line tolerance, and a transient sanitized `ingest_events` stage committed before buffer deletion. On merged `main`, `./scripts/tm.sh test` passed 29 tests / 257 assertions, including exactly-once, active/reclaimed lease, budget, resume, collector-contract, and IPv4/IPv6 staging-privacy coverage; Composer validation and the CI Compose configuration also passed before merge.

PR6 uses the owner-authorized resolution of open question 8: a zero-dependency collector classifier writes only derived bot/device/browser/OS fields. `Sessionizer`, `ReferrerNormalizer`, the internal classifier, an application-only MIT Public Suffix List parser, a versioned MPL-2.0 suffix list, and the runtime licence audit are implemented. On merged `main`, `./scripts/tm.sh test` passed 43 tests / 303 assertions; the Docker-run licence audit and strict Composer validation also passed.

PR7 — Aggregation & cardinality merged as pull request #16; GitHub issue #15 is closed. It adds all hourly dimensions, unique keys, idempotent staged-batch aggregation, a pure session-state transition with persistent cross-batch correction, cardinality folding, self-referral normalization, and durable cap warnings. On the reviewed branch, `./scripts/tm.sh test` passed 55 tests / 347 assertions and the Docker-run runtime licence audit passed; final review found no blockers.

PR8 — Rollups & retention merged as pull request #18; GitHub issue #17 is closed. It adds daily aggregate tables, exact `daily_visitors` materialized from the closed daily session state, idempotent `analytics:rollup`, and chunked retention that deletes hourly rows only after their matching daily rollup exists. On the reviewed branch, `./scripts/tm.sh test` passed 57 tests / 356 assertions and the Docker-run runtime licence audit passed; final review found no blockers.

PR9 — Dashboard API & SPA is ready for review; GitHub issue: #19. It adds bounded reporting queries over hourly and exact daily aggregates, period comparison, dimension breakdowns, an authenticated SPA entrypoint, Vue/TypeScript screens, and complete `en`/`pt-BR` text with accessible table alternatives and approximation/timezone labels. `./scripts/tm.sh test` passed 61 tests / 385 assertions; `vue-tsc`, the Vite build, and the Docker-run runtime licence audit also passed.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
