# TallyMark contributor guide

`docs/specs/tallymarkinitialspecandbuildplan.md` is the v0 authority. Implement its section 20 sequence one PR at a time, and open or update the linked GitHub issue before non-trivial work.

## Hard constraints

- Development is Docker-only. Never run host PHP, Composer, Node, or npm; use `scripts/tm.sh` or `scripts/tm.ps1`.
- Runtime dependencies must be usable under MIT, BSD-2-Clause, BSD-3-Clause, Apache-2.0, or ISC. GPL-only, LGPL-only, AGPL-only, and SSPL dependencies are prohibited.
- `matomo/device-detector` is LGPL-3.0-or-later and is forbidden. Do not add it.
- `public/px.php` is a standalone collector. It must never require `vendor/autoload.php`, boot Laravel, load Dotenv, instantiate a framework class, or open a database connection.
- The collector must not make DNS lookups or outbound HTTP, traverse the filesystem, write application logs, set/read cookies, or retain raw IP addresses or user agents.
- Do not use `exec`, `shell_exec`, `proc_open`, or `popen` in production runtime code.

## Architecture and coding

- Keep `app/Domain` framework-free and inject `Clock` into Domain and Application code.
- Put Eloquent models under `app/Infrastructure/Persistence/Eloquent`, never `app/Models`.
- Keep controllers thin; test tenant isolation explicitly.
- Shared collector/application formats are deliberately duplicated with counterpart comments; never autoload a shared package into the collector.
- Keep user-facing text in `frontend/src/i18n` or `lang/{en,pt_BR}`.

## Verification

- Run `./scripts/tm.sh test` before committing.
- Never commit `.env`, secrets, tokens, package mirror settings, `vendor`, or `node_modules`.
- Update `STATUS.md` and `BACKLOG.md` for every PR unit. Close the linked issue only after its verified definition of done is met.
