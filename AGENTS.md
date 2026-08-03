# Repository Guidelines

## Project Structure & Module Organization

TallyMark is specification-first; `docs/specs/tallymarkinitialspecandbuildplan.md` is authoritative. The planned application uses `app/Domain/` for framework-free logic, `app/Application/` for use cases, and `app/Infrastructure/` for persistence and integrations. Put Eloquent models in `app/Infrastructure/Persistence/Eloquent/`, not `app/Models/`. The standalone collector and tracker belong in `public/`; the Vue dashboard in `frontend/src/`; PHP tests in `tests/{Unit,Feature,Collector,Support}/`; and browser journeys in `frontend/e2e/`.

## Build, Test, and Development Commands

These planned wrappers are not committed yet. Development is Docker-only; do not run host PHP, Composer, Node, or npm.

- `./scripts/tm.sh up` (or `.\scripts\tm.ps1 up`) starts the local services.
- `./scripts/tm.sh test` runs the PHP unit, feature, and collector suites.
- `./scripts/tm.sh npm --prefix frontend run test` runs frontend tests.
- `./scripts/tm.sh npm --prefix frontend run build` builds the SPA.
- `./scripts/tm.sh e2e` runs Playwright in the node container.
- `./scripts/tm.sh load` measures collector throughput and guards the performance baseline.

## Coding Style & Naming Conventions

Use four spaces in PHP and two in TypeScript/Vue. Follow PSR-12 and Laravel naming: PascalCase classes, camelCase methods, and namespaces matching directories. Keep controllers thin, inject `Clock` for time, and keep Domain code free of Eloquent and facades. Localize user-facing text in `frontend/src/i18n/` or `lang/{en,pt_BR}/`, maintaining both languages. No formatter or linter is configured yet; follow nearby code until the scaffold defines one.

## Testing Guidelines

Use PHPUnit for PHP and Playwright for end-to-end coverage. Name tests `*Test.php` and `*.spec.ts`. Put pure logic in `tests/Unit`, SQLite-backed behavior in `tests/Feature`, and isolated `px.php` checks in `tests/Collector`. Cover tenant isolation, exactly-once ingestion, privacy, portability, and collector latency. No percentage target exists; exercise critical success and failure paths.

## Commit & Pull Request Guidelines

History uses short, imperative subjects without Conventional Commit prefixes, for example `Create tallymarkinitialspecandbuildplan.md`. Keep commits focused. Open or find a GitHub issue before non-trivial work, link it in the PR, and update `STATUS.md` once present. Describe scope, verification, risks, and documentation; include screenshots for UI changes. Leave `main` green and submit one planned PR unit at a time.

## Security & Dependency Rules

Never commit `.env` files, keys, tokens, or package-mirror settings. Runtime dependencies must use MIT, BSD-2/3-Clause, Apache-2.0, or ISC licenses; GPL-family and SSPL dependencies are prohibited. In particular, do not add `matomo/device-detector`. Keep `public/px.php` dependency-free: it must not boot Laravel, autoload Composer, connect to a database, or store raw IP addresses or user agents.
