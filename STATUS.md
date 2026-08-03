# TallyMark status

## Current implementation unit

PR1 — Tenancy, auth, isolation has passed final verification and is ready to merge. GitHub issue: #3. PR0 was merged as pull request #2.

PR1 adds ULID public IDs, tenant memberships, scoped audit logs, local session authentication, tenant policy/context middleware, and `platform:bootstrap-admin`. `./scripts/tm.sh test` passes 11 tests / 69 assertions, including cross-tenant 404 and global-scope isolation coverage.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
