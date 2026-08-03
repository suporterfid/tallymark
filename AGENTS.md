# TallyMark repository guide

The v0 authority is `docs/specs/tallymarkinitialspecandbuildplan.md`. Read it before implementation and follow its section 20 PR order.

For hard constraints, architecture, coding rules, security, and verification, read [CLAUDE.md](CLAUDE.md).

## Commands

- `./scripts/tm.sh up` or `.\scripts\tm.ps1 up` starts the local app stack.
- `./scripts/tm.sh bootstrap` creates `.env`, installs dependencies in Docker, and migrates the local database.
- `./scripts/tm.sh test` runs PHP tests in Docker.
- `./scripts/tm.sh npm --prefix frontend run test` and `build` run the future SPA checks in the node container.

## Layout

- `app/Domain` is framework-free; `app/Application` contains use cases.
- Eloquent models belong in `app/Infrastructure/Persistence/Eloquent`, never `app/Models`.
- The standalone collector is `public/px.php`; it must never autoload Composer, boot Laravel, or connect to a database.
- PHP tests live in `tests/{Unit,Feature,Collector,Support}`; browser tests will live in `frontend/e2e`.

## PR discipline

- Open or link the GitHub issue before non-trivial code.
- Keep each PR to one section 20 unit and leave `main` green.
- Update `STATUS.md` and `BACKLOG.md` with actual verification evidence.
- Never commit `.env`, credentials, package-mirror settings, `vendor`, or `node_modules`.
