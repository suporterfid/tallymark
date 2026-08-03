# TallyMark status

## Current implementation unit

PR2 — Sites & the site map file has passed final verification and is ready to merge. GitHub issue: #5. PR1 was merged as pull request #4.

PR0 and PR1 are merged. The owner decided that PR2's `storage/tm-sites.php` map omits `salt` until PR4 introduces the salts table and rotation. No provisional, static, or unrotated salt will be written. `./scripts/tm.sh test` passes 13 tests / 113 assertions, including regeneration after every site/site-host mutation, rotation identity preservation, and the post-transaction map boundary.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
