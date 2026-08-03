# TallyMark status

## Current implementation unit

PR2 — Sites & the site map file is blocked before implementation. GitHub issue: #5. PR1 was merged as pull request #4.

PR0 and PR1 are merged. PR2 cannot generate the specified `storage/tm-sites.php` map without a decision about its required current daily `salt`: section 7.3 includes it, while salts are introduced only in PR4. No provisional salt or incomplete map format has been implemented.

## Licence decision

The specification was amended with owner authorization: a dual-licensed dependency is acceptable when it offers a permitted option, TallyMark selects that option, and the selection is recorded from its authoritative licence file. `nette/schema` and `nette/utils` are used under their New BSD option; the audit record is in `docs/security/dependency-audit.md`.

GPL-only, LGPL-only, AGPL-only, and SSPL-only dependencies remain prohibited. The executable release audit is scheduled for PR14; the selected Nette licence options are covered by a regression test now.
