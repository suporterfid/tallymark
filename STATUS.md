# TallyMark status

## Current implementation unit

PR3 — Standalone collector is ready to merge. GitHub issue: #7. PR2 was merged as pull request #6.

PR3 adds a standalone `public/px.php` with no Composer, Laravel, or database path. Isolated bare-server tests verify 204/CORS, no cookie, unknown-key drops, host validation, DNT, bots, body caps, IPv4/IPv6 and raw-user-agent suppression, URL sanitization, concurrent shard caps, GIF fallback, and p99 PHP timing. `./scripts/tm.sh test` is green; Composer validation and the CI Compose configuration are also green.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
