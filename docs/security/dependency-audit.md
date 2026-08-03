# Dependency licence audit

## Policy

TallyMark is MIT licensed. A runtime dependency is acceptable only when it can be used under MIT, BSD-2/3-Clause, Apache-2.0, or ISC. For a dual-licensed package, this record identifies the permissive option selected by TallyMark and the authoritative upstream licence file used to verify it.

## Laravel 12 transitive dependencies

| Package | Locked version | Composer licence metadata | Selected option | Authoritative source |
|---|---:|---|---|---|
| `nette/schema` | `v1.3.5` | `BSD-3-Clause`, `GPL-2.0-only`, `GPL-3.0-only` | New BSD (`BSD-3-Clause`) | [upstream licence](https://github.com/nette/schema/blob/v1.3.5/license.md) |
| `nette/utils` | `v4.1.5` | `BSD-3-Clause`, `GPL-2.0-only`, `GPL-3.0-only` | New BSD (`BSD-3-Clause`) | [upstream licence](https://github.com/nette/utils/blob/v4.1.5/license.md) |
| `jeremykendall/php-domain-parser` | `6.4.0` | `MIT` | MIT | `vendor/jeremykendall/php-domain-parser/LICENSE` (verified) |

Both upstream licence files grant a choice between the New BSD licence and GPL v2/v3, and recommend BSD. TallyMark elects the New BSD option for both packages. This does not permit GPL-only, LGPL-only, AGPL-only, or SSPL-only dependencies.

## Enforcement

`scripts/license-audit.php` checks the locked runtime packages against the permitted licence set and requires every dual-licensed package to have a selected permissive option in `config/licence-selections.php`. Run it through the Docker-only toolchain:

```bash
./scripts/tm.sh composer exec -- php scripts/license-audit.php
```

## Bundled Public Suffix List data

`resources/data/public_suffix_list.dat` is the official Public Suffix List, version `2026-07-25_14-20-03_UTC` / commit `e1b8015c3b2f0f4f8c18659c2480fc1a22c07b20`. It is data, not a runtime dependency, and retains its source header and MPL-2.0 terms. The application-only `PdpRegistrableDomainResolver` loads it for referrer normalization; the standalone collector never loads it. Refresh it atomically through Docker with `./scripts/tm.sh composer exec -- php scripts/update-public-suffix-list.php`, which accepts only the official source URL and validates the MPL-2.0 header before replacing the file.
