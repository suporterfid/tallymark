# TallyMark status

## Current implementation unit

PR0 — Repo scaffold & Docker loop is ready for review. GitHub issue: #1.

Verified locally: `./scripts/tm.sh bootstrap` completed, `./scripts/tm.sh test` passed 5 tests / 40 assertions, `./scripts/tm.sh up` served HTTP 200, Compose configuration validated, and both wrapper scripts passed syntax checks. Laravel 12's required package set is permissible under the New BSD option documented by its dual-licensed Nette dependencies.

## Resolved licence question

The Laravel 12 runtime set includes `nette/schema` and `nette/utils`, each declaring `BSD-3-Clause`, `GPL-2.0-only`, and `GPL-3.0-only` in `composer.lock`. Their authoritative licence files grant the user a choice between the New BSD licence and GPL v2/v3 and recommend BSD. TallyMark uses the New BSD option, which satisfies section 4.4. The licence audit must treat alternatives in Composer's licence list as an OR: it accepts a package with at least one permitted option, and rejects a package with no permitted option.
