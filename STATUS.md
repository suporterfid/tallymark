# TallyMark status

## Current implementation unit

PR0 — Repo scaffold & Docker loop is blocked before completion. GitHub issue: #1.

The scaffold is implemented and its Docker-only test loop is working locally: `./scripts/tm.sh bootstrap` completed, `./scripts/tm.sh test` passes 6 tests / 45 assertions, `./scripts/tm.sh up` served HTTP 200, Compose configuration validated, and both wrapper scripts passed syntax checks. The inherited Composer development scripts that started persistent processes have been removed and are covered by a regression test.

## Blocker: lockfile licence rule

Laravel 12 requires `league/commonmark`, which requires `league/config`, which requires `nette/schema` and `nette/utils`. Both Nette packages list `BSD-3-Clause`, `GPL-2.0-only`, and `GPL-3.0-only` in `composer.lock`. Their licence files document a New BSD or GPL choice and recommend BSD, but section 17.5 literally requires the release audit to fail on *any* non-permissive licence in `composer.lock`. The spec does not state whether that rule should treat a dual-licence list as alternatives.

No audit interpretation has been chosen. PR0 will not be merged and PR1 will not start until this is clarified or the spec is amended.
