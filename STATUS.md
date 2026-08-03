# TallyMark status

## Current implementation unit

PR0 — Repo scaffold & Docker loop has passed final verification and is ready to merge. GitHub issue: #1.

The scaffold is implemented and its Docker-only test loop is working locally: `./scripts/tm.sh bootstrap` completed, `./scripts/tm.sh test` passes 7 tests / 53 assertions, `./scripts/tm.sh up` served HTTP 200, Compose configuration validated, and both wrapper scripts passed syntax checks. The inherited Composer development scripts that started persistent processes have been removed and are covered by a regression test.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
