# Backlog and open questions

## Open question: Laravel 12 and the lockfile licence rule

PR0 requires Laravel 12. Its dependency chain `laravel/framework` → `league/commonmark` → `league/config` reaches `nette/schema` and `nette/utils`; their `composer.lock` licence arrays contain both `BSD-3-Clause` and GPL entries. The upstream licence files offer New BSD or GPL v2/v3 and recommend BSD.

Section 4.4 permits only MIT, BSD-2/3-Clause, Apache-2.0, or ISC dependencies, while section 17.5 requires the release audit to fail on *any* non-permissive licence in `composer.lock`. Does the audit accept a dual-licensed package when a permitted option is available, or must Laravel 12 be replaced despite PR0 specifying it? The specification needs an explicit answer; PR0 remains blocked and no subsequent PR may start.
