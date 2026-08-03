# TallyMark

Privacy-first, cookieless web analytics for PHP + MySQL shared hosting. TallyMark separates a tiny standalone collector from a Laravel application so pageview collection never needs a framework boot or database connection.

## Development

Development is Docker-only. Do not run PHP, Composer, Node, or npm on the host.

```bash
./scripts/tm.sh bootstrap
./scripts/tm.sh up
./scripts/tm.sh test
```

On Windows, use `./scripts/tm.ps1` instead. The local application is served at `http://localhost:8060`; Mailpit is at `http://localhost:8045`; the demo site is at `http://localhost:8065`; and MySQL publishes on port `3309`.

## Design constraints

- Runtime dependencies must have a permitted MIT, BSD-2/3-Clause, Apache-2.0, or ISC licence option. `matomo/device-detector` is forbidden because it is LGPL.
- `public/px.php` will remain a standalone file: no Composer autoloader, Laravel boot, database connection, cookie, raw IP retention, or raw user-agent retention.
- Production work is cron-driven and uses MySQL/file-buffer primitives compatible with commodity shared hosting.

The implementation plan and full requirements are in [the specification](docs/specs/tallymarkinitialspecandbuildplan.md).

## Licence

TallyMark is licensed under the [MIT License](LICENSE).
