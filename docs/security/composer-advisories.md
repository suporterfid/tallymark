# Composer security advisories

## 2026-08-04 — Guzzle remediation

The network-backed `composer audit --locked --no-interaction` report identified two advisories for transitive `guzzlehttp/guzzle` 7.15.1, introduced by `laravel/framework`:

| Advisory | CVE | Severity | Affected versions | Resolution |
| --- | --- | --- | --- | --- |
| `PKSA-gcrk-3vtt-1r14` | `CVE-2026-69246` | High | `>=8.0.0,<8.0.1` or `<7.15.2` | Upgrade to 7.15.2 |
| `PKSA-cnw1-2ytm-cgr8` | `CVE-2026-69245` | Medium | `>=8.0.0,<8.0.1` or `<7.15.2` | Upgrade to 7.15.2 |

`laravel/framework` 12.64.0 accepts Guzzle `^7.8.2`. A Docker-only dry run resolved exactly one change, `7.15.1` to `7.15.2`; the update then completed and Composer reported no security advisories. Guzzle remains MIT licensed.

## Release gate

Both `tm` wrappers run `composer audit --locked --no-interaction --no-cache` before a release build. The check retries three times with bounded backoff and fails the release if Packagist cannot be reached or an advisory is reported. `--no-cache` is deliberate: a stale local advisory cache must never convert an unverifiable network result into a successful release.
