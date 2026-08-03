# Dependency licence audit

## Policy

TallyMark is MIT licensed. A runtime dependency is acceptable only when it can be used under MIT, BSD-2/3-Clause, Apache-2.0, or ISC. For a dual-licensed package, this record identifies the permissive option selected by TallyMark and the authoritative upstream licence file used to verify it.

## Laravel 12 transitive dependencies

| Package | Locked version | Composer licence metadata | Selected option | Authoritative source |
|---|---:|---|---|---|
| `nette/schema` | `v1.3.5` | `BSD-3-Clause`, `GPL-2.0-only`, `GPL-3.0-only` | New BSD (`BSD-3-Clause`) | [upstream licence](https://github.com/nette/schema/blob/v1.3.5/license.md) |
| `nette/utils` | `v4.1.5` | `BSD-3-Clause`, `GPL-2.0-only`, `GPL-3.0-only` | New BSD (`BSD-3-Clause`) | [upstream licence](https://github.com/nette/utils/blob/v4.1.5/license.md) |

Both upstream licence files grant a choice between the New BSD licence and GPL v2/v3, and recommend BSD. TallyMark elects the New BSD option for both packages. This does not permit GPL-only, LGPL-only, AGPL-only, or SSPL-only dependencies.
