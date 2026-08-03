# Backlog and open questions

No open questions are currently blocking PR0.

## Resolved: Laravel 12 and the lockfile licence rule

Laravel 12 reaches `nette/schema` and `nette/utils` through `league/commonmark` and `league/config`. The packages' authoritative licence files offer New BSD or GPL v2/v3 and recommend BSD. TallyMark uses the New BSD option. The release licence audit must therefore accept a package when its Composer licence list contains at least one permitted licence, and fail a package with no permitted option; it must continue to reject GPL-only, LGPL, AGPL, and SSPL packages.
