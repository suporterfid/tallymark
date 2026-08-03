# TallyMark — Initial Spec & First-Implementation Build Plan

> Privacy-first web analytics for the cPanel your grandpa never gave up. No cookies, no PII, no ClickHouse.

## Document Metadata

- **Version:** 1.0
- **Status:** Implementation-ready v0 specification + build plan
- **Target audience:** AI coding agents (Claude Code CLI), architects, maintainers, contributors
- **Proposed repository:** `suporterfid/tallymark` (public, **MIT**)
- **Primary deployment target:** PHP + MySQL shared hosting with cron (Hostinger, cPanel/LiteSpeed)
- **Reference deployment profile:** shared hosting without VPS, Redis, ClickHouse, Node runtime, or process supervisors
- **Development environment:** Docker only (no host PHP/Composer/Node)
- **Initial interface languages:** English (`en`) and Brazilian Portuguese (`pt-BR`)
- **Sibling projects:** [`taskconnect`](https://github.com/suporterfid/taskconnect) (architecture donor), [`grandpasson`](https://github.com/suporterfid/grandpasson) (identity), [`docmark`](https://github.com/suporterfid/convertdoctomd-php) (pure-PHP discipline), [`jotter`](https://github.com/suporterfid/jotter)

---

## 0. How a coding agent should use this document

1. Read this document end to end **before** writing code. It is the authority for v0.
2. Implement in the **PR sequence of §20**. One PR unit at a time. Do not start PR *n+1* before PR *n* is green.
3. Every requirement uses RFC-2119 keywords (§2). A **MUST** that is not met blocks the PR.
4. When this document and a later `README.md`/`STATUS.md` disagree, **this document wins** until explicitly amended.
5. The **hard constraints of §4 are non-negotiable** — especially §4.4 (licence purity) and §4.5 (the collector's zero-dependency rule). Reject any change that violates them and say so.
6. Keep `STATUS.md` and `BACKLOG.md` current as scope changes.
7. Where this document says *"mirror TaskConnect"*, read the referenced file before implementing — a deliberate, reviewed copy of a proven pattern, not a fresh invention.

---

## 1. Executive Summary

TallyMark is an open-source, multi-tenant, **cookieless web analytics** platform that runs on commodity PHP + MySQL shared hosting.

It has two deliberately separated halves:

1. **The collector** — a single, standalone, pure-PHP file that answers the tracking beacon. It boots no framework, loads no Composer autoloader, and **opens no database connection**. It appends one compact line to a rotating buffer file and returns `204`.
2. **The application** — a Laravel 12 + Vue 3 dashboard, plus per-minute cron commands that drain the buffer, aggregate into hourly counters, and roll up into long-term summaries.

That split is the entire product thesis. Analytics is a write-heavy workload arriving at unpredictable rates; shared hosting is the one environment where a heavyweight write path is fatal. Everything expensive happens on cron, not on the visitor's request.

### 1.1 Why this project exists (the gap)

| Project | Stack | Licence | Blocker |
|---|---|---|---|
| Matomo | PHP + MySQL | **GPL-3.0** | Copyleft; enormous feature surface; heavy on shared hosting |
| Open Web Analytics | PHP + MySQL | **GPL-2.0-or-later** | Copyleft; dated architecture |
| Koko Analytics | PHP + MySQL | **GPL-3.0** | Copyleft; **WordPress plugin only**, not standalone |
| Plausible | Elixir + ClickHouse + Postgres | AGPL-3.0 | Impossible on shared hosting |
| Umami | Node + Postgres/MySQL | MIT | Requires a Node runtime |
| Swetrix | Node + ClickHouse | AGPL-3.0 | Requires ClickHouse |
| Fathom | Closed source | Proprietary | Not self-hostable |

Every PHP + MySQL option is **copyleft**. Every permissively-licensed option **needs a runtime shared hosting does not have**. There is no MIT-licensed, standalone, PHP + MySQL, privacy-first analytics product designed for a €3/month cPanel plan. That is precisely the hole TallyMark fills.

Corroboration for the architecture: Koko Analytics — the closest existing thing — explicitly *bypasses WordPress entirely for its collection endpoint*. The same instinct, generalized, is TallyMark's foundation.

### 1.2 What is reused

The hard, already-solved parts come from `suporterfid/taskconnect`: cron-driven MySQL claiming, wall-clock tick budgeting, SSRF-safe DNS-pinned outbound HTTP, multi-tenant isolation, secret encryption and redaction, the shared-hosting release pipeline with its secret-hygiene scan, and the Docker-only `tc`-style wrapper. The pure-PHP discipline of the collector (strict types, PSR-12, **no `exec`/`shell_exec`/`proc_open`**, cPanel baseline extensions only) comes from `docmark`.

### 1.3 What is genuinely new

1. **A framework-free hot path with no database connection** (§7) — the defining constraint.
2. **Cookieless identity via a daily-rotating, self-destroying salt** (§8) — the defining privacy property.
3. **Cardinality control** (§10.4) — unbounded path/referrer cardinality destroys a shared-hosting database faster than raw volume does.
4. **A public, unauthenticated, high-volume write endpoint** — an abuse target that must be hardened without a database to rate-limit against (§9).

---

## 2. Normative Language

**MUST / MUST NOT** — mandatory for the stated release; a violation blocks merge.
**SHOULD / SHOULD NOT** — strongly recommended; deviation requires a documented reason in the PR description.
**MAY** — optional.

Unless explicitly marked deferred, requirements apply to **v0**.

---

## 3. Mission and Scope

### 3.1 Mission

Give a self-hoster on commodity shared hosting a credible replacement for Google Analytics: useful traffic insight, no consent banner, no data leaving their server, no monthly bill — and a licence that lets them build a product on top of it.

### 3.2 Primary goals

- Cookieless, PII-free measurement that needs no consent banner in the common case (§8.5).
- A tracking script under 1 KB gzipped that does not measurably affect page load.
- A collection endpoint that survives a traffic spike on a shared host.
- A dashboard that answers the questions people actually ask: how many, from where, to which pages, and did they convert.
- Multi-tenant isolation strong enough to host several unrelated sites, or clients, on one installation.
- Deployable by uploading a zip and setting one cron line.
- First-class delegated identity via GrandpaSSOn and first-class interoperation with TaskConnect.

### 3.3 Secondary goals

- Custom events and goals with conversion counts.
- UTM campaign attribution.
- A public, shareable dashboard per site (opt-in).
- Brazilian Portuguese and English UI from day one.

### 3.4 Non-goals (v0 — explicit, prevents scope creep)

TallyMark v0 **MUST NOT** attempt:

- Cross-site or cross-device user tracking, user profiles, or cohorts.
- Session recording, heatmaps, scroll maps, or form analytics.
- A/B testing or feature flags.
- Arbitrary cross-dimension filtering (§10.3 explains the deliberate trade-off).
- Real-time streaming dashboards. Data is near-real-time with a documented lag (§7.6).
- Individual visitor timelines. The product **cannot** produce one by construction, and this is a feature.
- E-commerce revenue attribution or funnels beyond single-step goals.
- IP-address storage, ever, in any form, for any duration (§8.2).
- A hosted/SaaS control plane.

---

## 4. Hard constraints (the walls)

### 4.1 Must stay deployable on commodity shared hosting

Production capability assumed is exactly: **PHP 8.2+, MySQL 8.0+, a per-minute cron, and a document root pointed at `public/`.**

- **No always-on process, daemon, or broker.** Redis, Memcached, RabbitMQ, ClickHouse, Kafka, Horizon, Reverb, Octane, Supervisor-managed workers, and equivalents **MUST NOT** become required.
- All asynchronous work **MUST** use file buffers plus MySQL-backed claiming driven by per-minute cron.
- `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` defaults **MUST** remain database/file/sync-friendly.
- `exec()`, `shell_exec()`, `proc_open()`, and `popen()` **MUST NOT** be used at runtime. Hostinger disables them. (Consequence: deploys create the storage symlink with `ln -sfn`, as TaskConnect's `scripts/deploy.sh` already does.)
- Assumed extensions **MUST** be cPanel-baseline: `pdo_mysql, mbstring, json, session, curl, zlib, openssl, intl, bcmath, zip, dom, fileinfo, filter, hash, tokenizer, xml, ctype, pcre`.

### 4.2 Development is Docker-only

PHP, Composer, Node, and npm **MUST NOT** be installed or run on the host. Everything runs through containers via the `tm` wrapper (§16).

### 4.3 Track all work on GitHub issues

Every feature request, user story, plan, task, and bug **MUST** be represented and kept current as a GitHub issue in `suporterfid/tallymark`. Open or find the issue before non-trivial work; link the PR; close with a reason. Planning docs under `docs/` **MUST** stay in sync; the issue is canonical.

### 4.4 Licence purity (hard requirement — this project's differentiator)

TallyMark's entire market position is *"the MIT one"*. A copyleft dependency destroys that position.

- The repository **MUST** be MIT licensed.
- Every runtime dependency **MUST** be usable under MIT, BSD-2/3-Clause, Apache-2.0, or ISC. A dual-licensed dependency is permitted only when it offers at least one of those licences, TallyMark explicitly selects that permissive option, and the selection is verified from the package's authoritative `LICENSE` file and recorded in `docs/security/dependency-audit.md`. A dependency with no permitted option — including GPL-only, LGPL-only, AGPL-only, or SSPL-only packages — **MUST NOT be introduced**, including transitively.
- **`matomo/device-detector` is LGPL-3.0-or-later and MUST NOT be used**, despite being the obvious choice for user-agent parsing. This is the single most likely licence mistake in this project; it **MUST** be named in `CLAUDE.md` and in `.cursor/rules/`.
- Verified-acceptable alternatives: `jaybizzle/crawler-detect` (**MIT**) for bot detection. For browser/OS/device classification, prefer a **small internal classifier** (§10.2) over any dependency; if one is added, its licence **MUST** be verified from its `LICENSE` file — not from a blog post or an aggregator — and recorded in `docs/security/dependency-audit.md`.
- A **licence audit MUST run as part of `tm release`** and **MUST** fail the build when a package in `composer.lock` offers no permitted MIT, BSD-2/3-Clause, Apache-2.0, or ISC licence option. A package's Composer licence list represents alternatives: a dual-licensed package is accepted only when it has a permitted option and that option is recorded in `docs/security/dependency-audit.md`; a package with only non-permissive options fails. This is enforcement, not documentation.
- Bundled data files carry their own terms. Geo databases in particular **MUST NOT** be vendored (§10.5).

### 4.5 The collector is not a Laravel application

`public/px.php` **MUST NOT**:

- `require` Composer's `vendor/autoload.php`;
- boot Laravel, load `.env` through Dotenv, or instantiate any framework class;
- open a database connection, in any code path, including error handling;
- perform DNS lookups, outbound HTTP, or filesystem traversal;
- write anything to `storage/logs` on the request path.

It **MUST** be a self-contained file with `declare(strict_types=1)`, reading its configuration from one generated PHP file (§7.3). Its measured p99 **MUST** stay under 5 ms of PHP time excluding network. A performance test **MUST** assert the absence of `vendor/autoload.php` and of any PDO/mysqli symbol in its execution path.

---

## 5. Architecture

### 5.1 The two halves

```text
┌─────────────────────────── visitor's browser ──────────────────────────┐
│  tm.js  (<1 KB gz, no cookies, sendBeacon)                             │
└───────────────────────────────┬────────────────────────────────────────┘
                                │ POST /px.php   (~2 ms, no DB)
┌───────────────────────────────▼────────────────────────────────────────┐
│  COLLECTOR — standalone pure PHP, no framework, no DB                  │
│  validate → hash visitor → append line to buffer/<minute>-<shard>.ndjson│
│  → 204 No Content                                                      │
└───────────────────────────────┬────────────────────────────────────────┘
                                │ closed minute files only
┌───────────────────────────────▼────────────────────────────────────────┐
│  CRON (Laravel artisan, per minute)                                    │
│  analytics:ingest   drain buffers → sessionize → bot filter → UPSERT   │
│  analytics:rollup   hourly → daily summaries, cache dashboards         │
│  analytics:maintenance  salt rotation, retention, heartbeat (hourly)   │
└───────────────────────────────┬────────────────────────────────────────┘
┌───────────────────────────────▼────────────────────────────────────────┐
│  MySQL: hourly per-dimension counters + daily rollups                  │
│  Laravel 12 API  ←  Vue 3 SPA dashboard                               │
└────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Stack decisions

| Concern | Decision | Rationale |
|---|---|---|
| Collector | Standalone PHP 8.2, zero dependencies | §4.5 — the product's core constraint |
| Backend | Laravel 12, Domain/Application/Infrastructure layering | Mirrors TaskConnect; direct pattern reuse |
| Dashboard | Vue 3 + TS + Vite + Pinia + vue-router + vue-i18n + Tailwind v4 | Mirrors TaskConnect and Jotter |
| Public shared dashboard | Server-rendered Blade | Cheap, cacheable, no SPA bundle |
| Database | MySQL 8.0+ in production; SQLite `:memory:` in tests | Mirrors TaskConnect's `phpunit.xml` |
| Hot-path durability | Append-only sharded buffer files | §7.2 |
| Identity | Local email/password **plus** GrandpaSSOn (dual-mode, opt-in) | §14 |
| Outbound safety | DNS-pinned transport + `OutboundPolicy`, ported | §15.2 |
| Tracker script | Hand-written vanilla JS, no build framework | Must stay under 1 KB gzipped |
| CI | None. Local verification through Docker | Mirrors TaskConnect |

### 5.3 Layering

```text
app/
  Domain/              pure logic, framework-free — no Eloquent, no facades
    Collection/          EventLine parsing/serialization, Sessionizer
    Identity/            VisitorHasher, SaltRotation
    Classification/      BotClassifier, DeviceClassifier, ReferrerClassifier
    Aggregation/         DimensionKey, CardinalityGuard, BucketMath
    Reporting/           MetricCalculator, ComparisonPeriod
    Outbound/            OutboundPolicy, UrlValidator, IpClassifier
    Shared/              Clock, PublicId, TenantRole
  Application/
    Ingest/              BufferReader, IngestRunner, TickBudget, IngestClaimer
    Rollups/             RollupService, RetentionService
    Sites/, Goals/, Reports/, Members/, ApiKeys/, Audit/, Tenancy/, Auth/
    Notifications/       DigestReportSender, GoalWebhookDispatcher, TaskConnectChannel
    GrandpaSson/         token + introspection clients (ported, corrected)
  Infrastructure/
    Persistence/Eloquent/   ALL Eloquent models here, not app/Models/
    HttpClient/             GuzzlePinnedHttpTransport
    Geo/                    CountryResolver implementations
  Http/
    Controllers/Api/V1/     thin controllers
    Controllers/Public/     shared dashboards (Blade)
  Console/Commands/
  Policies/

public/
  px.php               THE COLLECTOR — standalone, zero dependencies
  tm.js                tracking script (served with long cache headers)
  index.php            Laravel front controller
```

### 5.4 Coding rules

- Controllers **MUST NOT** contain core business logic.
- Sessionization, bot classification, and metric calculation **MUST** be pure and testable without HTTP or a database.
- Time **MUST** come from the injected `Clock`. `now()`, `time()`, and `new DateTime()` **MUST NOT** appear in Domain or Application code. *(The collector is exempt — it has no container; it takes the timestamp once at entry.)*
- New Eloquent models **MUST** go in `app/Infrastructure/Persistence/Eloquent/`.
- Tenant scoping **MUST NOT** depend on a developer remembering a `where` clause. Use global scopes plus explicit isolation tests.
- User-facing text **MUST NOT** be hard-coded in Vue components or Blade templates.
- Any code shared between the collector and the application **MUST** be duplicated deliberately, with a comment naming the counterpart, rather than extracted into a Composer package the collector would have to autoload. **The `EventLine` format is the contract between them and MUST have a single round-trip test exercising both implementations.**

---

## 6. Domain model

```text
Tenant
└── Site                        one tracked website; the isolation + reporting boundary
    ├── Goal                    a named conversion (event name or URL pattern)
    ├── SharedDashboard         optional public read-only view
    ├── hourly counters         per-dimension aggregates (the write target)
    ├── daily rollups           long-retention summaries
    └── ScheduledReport         emailed digest
Salt                            daily-rotating, self-destroying visitor salt
IngestBatch                     a claimed buffer file, for exactly-once ingestion
```

- **Site** — a tracked website: name, hostname(s), timezone, public/private, exclusion rules. Its `site_key` is the public token embedded in the tracking script.
- **Goal** — a named conversion, matched by custom event name or by URL pattern.
- **Salt** — the daily secret used to derive visitor hashes (§8). Rotated and destroyed.
- **IngestBatch** — one claimed buffer file with a lease, so overlapping cron runs cannot double-count (§7.5).

### 6.1 Identifier strategy

Mirror `taskconnect app/Domain/Shared/PublicId.php`: every table has an internal auto-increment `id` (never exposed) and a unique ULID `public_id` used in the API. Prefixes: `ten_`, `site_`, `goal_`, `dash_`, `rep_`.

`site_key` is **separate** from `site.public_id`: it is a short, high-entropy, rotatable public token that appears in the tracking snippet on every visitor's page. Rotating it **MUST NOT** change the site's identity or history.

---

## 7. The collector (hot path)

The most important component. Everything here is optimized for one property: **finish fast and never fail the visitor**.

### 7.1 Request contract

```
POST /px.php          Content-Type: text/plain  (avoids a CORS preflight)
GET  /px.php?...      fallback for <noscript> pixel and beacon-less clients
```

Request body is compact JSON:

```json
{ "k":"<site_key>", "u":"https://example.com/pricing?utm_source=x",
  "r":"https://news.ycombinator.com/", "e":"pageview",
  "n":"Signup", "p":{"plan":"pro"}, "w":1920 }
```

Requirements:

- The endpoint **MUST** always respond `204 No Content` with an empty body on any outcome that is not a hard protocol error — valid, invalid, rejected, shed, or buffer failure alike. It **MUST NOT** reflect any input, disclose whether a `site_key` exists, or return a diagnostic body. Silence is both a privacy property and an abuse-resistance property.
- It **MUST** cap the request body at `TM_MAX_BODY_BYTES` (default 2048) and abandon anything larger without parsing.
- It **MUST** send `Access-Control-Allow-Origin: *` and no credentials, so it works cross-origin without a preflight.
- It **MUST NOT** set, read, or require any cookie. A test **MUST** assert no `Set-Cookie` header is ever emitted.
- `GET` **MUST** additionally support responding with a 1×1 transparent GIF when `Accept` indicates an image, for `<noscript>` use.

### 7.2 Buffer files

The collector appends one NDJSON line to:

```
storage/tm-buffer/<YYYYMMDDHHmm>-<shard>.ndjson
```

- `shard` **MUST** be chosen at random from `TM_BUFFER_SHARDS` (default 4) to spread lock contention.
- The write **MUST** use `file_put_contents($path, $line, FILE_APPEND | LOCK_EX)`. `LOCK_EX` is specified deliberately: sub-`PIPE_BUF` `O_APPEND` writes are atomic on local Linux filesystems, but that guarantee does not hold on NFS, which some shared hosts use for `storage/`. Correctness on the weakest plausible filesystem wins over the marginal speed of dropping the lock.
- On any write failure the collector **MUST** silently drop the event and still return `204`. Analytics data is not worth a visible error on someone's website.
- The buffer directory **MUST** be outside the document root, or protected by a `deny from all` `.htaccess`. A test **MUST** assert the buffer is not web-readable.

### 7.3 Configuration without a database

The collector cannot query MySQL, so it reads a **generated PHP map file**:

```php
// storage/tm-sites.php — regenerated by cron whenever sites change
return [
  'salt' => '…current daily salt…',
  'sites' => [
    'ab12cd34' => ['id' => 7, 'hosts' => ['example.com','www.example.com'], 'sample' => 100],
  ],
];
```

- Regenerated by `analytics:maintenance` and immediately on any site mutation, written atomically (write to a temp file, then `rename()`).
- Returned by `require`, so OPcache holds it in memory — effectively zero-cost after the first request.
- An unknown `site_key` **MUST** be dropped without buffering. This is what stops a leaked endpoint from becoming an open write sink.
- The request's `Origin`/`Referer` hostname **MUST** be checked against the site's registered hosts, with a configurable per-site option to disable the check (needed for apps, AMP, and reverse proxies). Mismatches are dropped.

### 7.4 Cheap prefilters on the hot path

Only filters that cost microseconds run here; authoritative classification happens at ingest (§10.2).

- A short substring check against an obvious-bot list (`bot`, `crawl`, `spider`, `preview`, `headless`, `curl/`, `wget`).
- `DNT: 1` / `Sec-GPC: 1` **MUST** be honoured when `TM_RESPECT_DNT` is enabled (default **on**), dropping the event.
- Per-site sampling: drop when `random_int(1,100) > sample`.

### 7.5 Ingestion is exactly-once

`analytics:ingest` runs every minute:

- It **MUST** only process files whose minute bucket is **strictly in the past**, so no collector process is still appending to them. This eliminates the torn-read problem entirely without locking, at the cost of up to 60 s of latency (§7.6).
- Each file **MUST** be claimed via an `ingest_batches` row with a unique key on the filename, using the MySQL claim-lease pattern from `taskconnect app/Application/Scheduling/DueTaskClaimer.php`. Two overlapping cron runs **MUST NOT** double-count a file.
- A file **MUST** be deleted only after its aggregates are committed, inside or after the same transaction. A crash between commit and delete **MUST** result in the file being skipped on retry (the batch row already records completion), never re-aggregated.
- Malformed lines **MUST** be counted and skipped, never abort the batch.

### 7.6 Latency and backpressure

- Dashboard data lags real time by **up to ~2 minutes** (one minute for the bucket to close, one for ingest). This **MUST** be stated in the UI and the docs. It is not a bug to be fixed by writing synchronously.
- The buffer directory **MUST** have a hard size cap (`TM_BUFFER_MAX_MB`, default 64). Beyond it the collector **MUST** shed new events and increment a shed counter in a sentinel file. Shedding protects the account's disk quota; silently filling the quota would take down the customer's entire website, which is far worse than losing analytics data.
- Shed events **MUST** surface as a prominent dashboard warning. Silent data loss is unacceptable even when the loss itself is the correct behaviour.
- `analytics:ingest` **MUST** respect a wall-clock budget (`TM_INGEST_TARGET_SECONDS`, default 45, capped by `max_execution_time` minus a safety margin), mirroring `taskconnect TickBudget`, and process oldest buffers first so a backlog drains in order.

---

## 8. Privacy and cookieless identity

This is the product's ethical core and its main marketing claim. It **MUST NOT** be compromised for a feature.

### 8.1 Visitor identification

TallyMark **MUST** derive a daily visitor hash exactly as the established privacy-first products do:

```
visitor_id = BLAKE2b/SHA-256( daily_salt || site_id || client_ip || user_agent )   → 64-bit prefix
```

- The salt **MUST** be at least 256 bits from a CSPRNG.
- The salt **MUST** rotate every 24 hours, and the previous salt **MUST** be destroyed after a short grace overlap (default 1 hour) needed only to close sessions spanning the boundary.
- Once destroyed, yesterday's hashes are unlinkable to today's — **by anyone, including the installation's own operator**. This is what makes the identifier non-persistent rather than merely pseudonymous.
- The hash **MUST** be truncated to 64 bits. Full-width hashes are unnecessary for counting and increase re-identification surface.
- Salt rotation **MUST** be verified by `analytics:maintenance` on every run, not merely scheduled once — a missed rotation is a silent privacy regression, so a stale salt **MUST** raise an operator alarm.

### 8.2 What is never stored

The following **MUST NOT** be written to the buffer file, the database, the logs, or any cache, at any time:

- the client IP address, in full, truncated, hashed alone, or anonymized;
- the raw `User-Agent` string;
- any cookie, `localStorage`, or `sessionStorage` value;
- URL query parameters other than a configured allowlist (default: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref`);
- URL fragments;
- `Authorization` headers or any request header not explicitly enumerated in this spec.

The IP and User-Agent exist **only** as local variables inside a single collector invocation: consumed to produce the visitor hash, the country code, and the device classification, then discarded when the process ends. A test **MUST** assert no buffer line and no database column can contain an IP-shaped string.

### 8.3 URL sanitization

- Query strings **MUST** be stripped to the allowlist before the URL reaches the buffer. This happens in the **collector**, not at ingest — an unsanitized URL must never touch disk.
- Paths **MUST** be truncated to `TM_MAX_PATH_BYTES` (default 512).
- Sites **MAY** configure path-normalization rules (e.g. `/orders/\d+` → `/orders/:id`) applied at ingest, to control cardinality (§10.4) and to avoid recording identifiers embedded in paths.

### 8.4 Data subject considerations

Because no personal data is retained, there is no individual record to export or erase — TallyMark **cannot** answer "show me everything about this person", by construction. The docs **MUST** state this plainly as a design property, and **MUST NOT** frame it as a limitation to be worked around.

### 8.5 Legal positioning (state properties, not legal advice)

`docs/privacy.md` **MUST**:

- describe precisely what is collected, derived, retained, and discarded;
- state that the operator, not the TallyMark project, is the data controller;
- note that cookieless, PII-free, aggregate-only measurement is the basis on which comparable products assert that no consent banner is required under the GDPR/ePrivacy and the Brazilian LGPD — **and that this is a common position, not a guarantee**, since it depends on the deployment, the jurisdiction, and the operator's other processing;
- recommend that operators seek their own advice rather than relying on the project's characterization.

The project **MUST NOT** publish an unqualified claim such as "GDPR compliant" or "no consent banner needed" as though it were a property the software can confer on its users.

---

## 9. Collector security

An unauthenticated public write endpoint with no database is an unusual threat surface.

- **Unknown `site_key` → dropped** before any write (§7.3). This is the primary defence.
- **Host validation** against registered hostnames, per site, defaulting on.
- **Body cap** at 2048 bytes, enforced before parsing.
- **Field caps**: event name ≤ 64 bytes; each prop key ≤ 32 and value ≤ 128 bytes; at most 8 props. Excess is truncated, not rejected.
- **Rate limiting without a database.** The collector cannot consult MySQL. It **MUST** therefore rely on: per-site sampling, the global buffer cap (§7.6), and a coarse per-minute line-count ceiling per shard file (`TM_MAX_LINES_PER_MINUTE`, default 20000) checked cheaply via `filesize()`. Precise per-IP limiting **MUST** happen at ingest, where the visitor hash is available and the database is not on the critical path — abusive hashes are discarded before aggregation.
- **No reflection.** The response is always empty; no input is echoed in headers or body.
- **Prop values MUST be treated as untrusted** everywhere downstream: escaped on render, never interpolated into SQL, never used as a filename or a cache key without hashing.
- **Timing uniformity.** Valid and invalid `site_key`s **MUST** follow the same code path length as far as practical, so the endpoint is not a key-enumeration oracle.
- The tracking script and endpoint **MUST** work under a strict `Content-Security-Policy`, and `docs/` **MUST** publish the exact `script-src`/`connect-src` directives an operator needs.

---

## 10. Ingestion, classification, aggregation

### 10.1 Sessionization

- A **session** is a sequence of events from one `visitor_id` on one site with no gap longer than `TM_SESSION_GAP_MINUTES` (default 30), derived at **ingest**, not in the browser.
- Because the visitor hash is destroyed daily, sessions **MUST NOT** span the salt rotation. A session crossing midnight is closed and a new one begins. The docs **MUST** state this, since it slightly inflates session counts for sites with overnight traffic — an honest, disclosed consequence of the privacy design.
- **Bounce** = a session with exactly one pageview.
- **Session duration** = last pageview timestamp − first pageview timestamp. The dwell time of the final page is unknowable without an unload beacon; v0 **MUST** report duration on this basis and **MUST** document the approximation rather than inventing an estimate.

### 10.2 Classification

Runs at ingest, never on the hot path.

- **Bots** — `jaybizzle/crawler-detect` (**MIT**, verified). Bot traffic **MUST** be excluded from all reported metrics and **SHOULD** be counted separately so operators can see what was filtered.
- **Device / browser / OS** — a **small internal classifier** over a curated regex table, kept deliberately coarse: device in `{desktop, mobile, tablet, tv, bot, unknown}`, plus browser family and OS family. `matomo/device-detector` **MUST NOT** be used (§4.4). Coarse-but-MIT beats precise-but-LGPL, and the accuracy difference is immaterial at dashboard granularity.
- **Referrer** — normalized to a registrable domain, with `utm_source` taking precedence when present. Self-referrals (matching the site's own hosts) **MUST** be classified as direct. A small bundled spam-referrer blocklist **SHOULD** be applied and **MUST** be operator-overridable.

### 10.3 The aggregation model (and its deliberate trade-off)

Aggregates are stored as **per-dimension hourly counters**, not as one wide fact table:

```text
stats_hourly_pages     (site_id, hour, path)            → pageviews, visitors, bounces, duration_sum
stats_hourly_referrers (site_id, hour, referrer)        → pageviews, visitors
stats_hourly_countries (site_id, hour, country)         → pageviews, visitors
stats_hourly_devices   (site_id, hour, device, browser, os) → pageviews, visitors
stats_hourly_campaigns (site_id, hour, source, medium, campaign) → pageviews, visitors
stats_hourly_events    (site_id, hour, event_name)      → count, visitors
stats_hourly_totals    (site_id, hour)                  → pageviews, visitors, sessions, bounces, duration_sum
```

**The trade-off, stated plainly:** this model cannot answer *"pageviews on `/pricing` from Brazil on mobile"*. Arbitrary cross-dimension filtering is exactly what requires a columnar store like ClickHouse, which is what makes Plausible and Swetrix un-hostable on shared hosting. TallyMark trades that capability for the ability to run on a €3/month plan. This **MUST** be stated in the README as a deliberate positioning decision, not hidden as a missing feature.

Counting distinct visitors per bucket from pre-aggregated counters is not exact. v0 **MUST** compute visitor counts by carrying a per-bucket set of visitor hashes through the ingest batch and counting distinct values **within that batch**, then summing — which over-counts a visitor active in multiple hours. The dashboard **MUST** therefore label the daily figure "visits" rather than "unique visitors" unless a daily-distinct pass is performed by `analytics:rollup`, which **MUST** maintain a `daily_visitors` set table for exactness at day granularity. Anything else is a fabricated number, and fabricated numbers are worse than absent ones.

### 10.4 Cardinality control (mandatory)

Unbounded distinct paths or referrers will destroy a shared-hosting database faster than raw volume. Therefore:

- Each dimension table **MUST** enforce a per-site, per-hour distinct-value cap (`TM_MAX_DISTINCT_PER_BUCKET`, default 500 for paths, 200 for others).
- On overflow, further values **MUST** be folded into a reserved `(other)` bucket rather than inserted. `(other)` **MUST** be visibly labelled in the UI so operators know truncation occurred.
- Sites **SHOULD** be able to define path-normalization rules (§8.3) to keep cardinality low deliberately.
- `analytics:maintenance` **MUST** warn when a site repeatedly hits the cap — that is the signal its paths need normalization.

### 10.5 Country resolution (no vendored geo database)

Geo data licensing is a trap for an MIT project, and geo database files are large enough to matter against shared-hosting quotas.

- A geo database **MUST NOT** be vendored into the repository or the release zip. MaxMind's GeoLite2 requires an account, a licence key, and a signed EULA, and its redistribution terms are restrictive; DB-IP Lite is CC BY, requiring attribution.
- Country resolution **MUST** be a pluggable `CountryResolver` interface with these implementations, in preference order:
  1. **`HeaderCountryResolver`** (default) — reads an edge-provided header such as `CF-IPCountry` (Cloudflare) or `X-Geo-Country`. Zero cost, zero licence exposure, and already available to a large share of the target audience.
  2. **`NullCountryResolver`** — geo disabled; country reported as `unknown`. **MUST** be the fallback when nothing is configured, and the product **MUST** be fully functional without geo.
  3. **`MaxMindCountryResolver`** / **`DbIpCountryResolver`** — opt-in, with the operator supplying the database file and accepting its terms. Docs **MUST** state the attribution obligation.
- Because resolution must happen in the collector (the IP dies there, §8.2), the resolver used on the hot path **MUST** be header-based or a memory-mapped lookup that costs no framework boot. A resolver requiring Composer autoloading **MUST** run in a variant collector explicitly enabled by the operator, with the performance cost documented.

---

## 11. Reporting and the dashboard

### 11.1 Metrics (v0)

Visitors, pageviews, sessions, bounce rate, average session duration, views per session; top pages, entry pages, referrer sources, countries, devices/browsers/OS, campaigns; goals with conversion count and rate; period comparison against the previous equivalent period.

### 11.2 Requirements

- The default view **MUST** load from pre-aggregated tables only, never by scanning event-level data. A test **MUST** assert a bounded query count on the dashboard endpoint.
- Every figure that is an approximation (session duration, `(other)` buckets, cross-midnight sessions, sampled sites) **MUST** be marked in the UI with an explanation. A dashboard that quietly presents approximations as exact is the failure mode this section exists to prevent.
- Timezone **MUST** be per-site; hourly buckets are stored in UTC and converted for display.
- The dashboard **MUST** surface: ingest heartbeat freshness, shed-event warnings, and cardinality-cap warnings.
- A **shared dashboard** (public, read-only, opt-in per site) **MUST** be server-rendered Blade, cached, and **MUST NOT** expose the `site_key`, hostnames beyond the site's display name, goal definitions, or any operator identity.

### 11.3 Data export

- CSV export of any report table, and a JSON API mirroring the dashboard queries.
- A **raw-buffer export MUST NOT** exist — there is no event-level personal data to export, and offering one would imply otherwise.

---

## 12. Data model and retention

### 12.1 Tables

```text
users, tenants, tenant_memberships, user_preferences, audit_logs, api_keys
sites, site_hosts, goals, shared_dashboards, scheduled_reports
salts, ingest_batches, ingest_stats
stats_hourly_totals, stats_hourly_pages, stats_hourly_referrers, stats_hourly_countries,
stats_hourly_devices, stats_hourly_campaigns, stats_hourly_events
stats_daily_*            (same shape, day buckets)
daily_visitors           (site_id, day, visitor_hash) — for exact daily distinct counts
rate_limit_buckets, system_heartbeats
```

### 12.2 Required indexes

- Every `stats_hourly_*` table **MUST** have a **unique** key on `(site_id, hour, <dimension columns>)` — this is what makes `INSERT … ON DUPLICATE KEY UPDATE` correct and idempotent, and it is the difference between a re-run that is safe and one that silently doubles every number.
- `ingest_batches (filename)` unique — exactly-once ingestion.
- `daily_visitors (site_id, day, visitor_hash)` unique, and `(site_id, day)` for counting.
- `stats_daily_* (site_id, day, …)` unique.
- `sites (site_key)` unique.

### 12.3 Retention defaults

| Data | Env var | Default |
|---|---|---|
| Buffer files | — | deleted on successful ingest; orphans purged after 24 h |
| `daily_visitors` | `RETENTION_DAILY_VISITORS_DAYS` | 60 |
| Hourly stats | `RETENTION_STATS_HOURLY_DAYS` | 180 |
| Daily stats | `RETENTION_STATS_DAILY_DAYS` | 1825 (5 years) |
| Ingest batches | `RETENTION_INGEST_BATCHES_DAYS` | 30 |
| Audit logs | `RETENTION_AUDIT_LOGS_DAYS` | 365 |
| Salts | — | destroyed after rotation + grace (§8.1) |

- Hourly stats **MUST NOT** be deleted before the covering daily rollup exists.
- Deletion **MUST** be chunked (`DELETE … LIMIT`) with a per-tick cap, so retention never holds a long lock on a shared host.
- Because aggregates carry no personal data, long retention is a **feature** — five years of daily history costs a few megabytes. This is a genuine advantage over event-level products and **SHOULD** be stated as such.

---

## 13. Performance and capacity

### 13.1 Targets (single shared-hosting account)

| Metric | Target |
|---|---|
| Collector p99 PHP time | < 5 ms |
| Tracking script size | < 1 KB gzipped |
| Pageviews per day | 1,000,000 |
| Peak sustained | 200 events/second |
| Sites per installation | 100 |
| Ingest tick | ≤ 45 s claiming, ≤ 55 s total |
| Dashboard query time | < 200 ms, bounded query count |
| Database growth | < 50 MB/year for a 100k-pageview/month site |

These are what the acceptance checklist verifies. Docs **MUST** state that shared hosts vary and some throttle cron below a true one-per-minute cadence.

### 13.2 Load fixture

A reproducible load fixture **MUST** ship in the repo — generating a synthetic buffer of N events and running `analytics:ingest` against it — so throughput claims are measured rather than asserted, and regressions are caught.

---

## 14. GrandpaSSOn integration (identity)

TallyMark **MUST** ship with the GrandpaSSOn seam present and working from v0, defaulted off, mirroring TaskConnect's dual-mode design.

> The contract below was verified against the broker's routing table (`grandpasson app/Http/AppRoutes.php`) and controllers, not against prose. §14.6 lists traps that will otherwise cost days.

### 14.1 Dual-mode principle

Local email/password authentication **MUST** remain the default and **MUST** keep working with the broker absent. Both modes are independently gated by `GRANDPASSON_OUTBOUND_ENABLED` and `GRANDPASSON_INBOUND_ENABLED`, both defaulting to `false`, via a `config/grandpasson.php` mirroring TaskConnect's — with `read_scope`/`write_scope`/`callback_scope` defaulting to `analytics:read`, `analytics:write`, `analytics:callback`, and an added `introspection_cache_seconds` (§14.4).

**The collector is entirely outside this.** It has no authentication, no session, and no identity concept. GrandpaSSOn governs the dashboard only.

### 14.2 Two distinct client registrations

The broker keeps **two separate client tables**; a client in one cannot act as the other. TallyMark needs both:

| Need | Table | Created with |
|---|---|---|
| Browser login (RP code flow) | `oauth_clients` | `php cron/seed_oauth_client.php --client-id=… --redirect-uri=… --secret=…` |
| Machine tokens + introspection | `service_clients` | `php cron/admin.php client:create-service "TallyMark" --scopes=… --aud=…` |

### 14.3 Inbound A — delegated browser login

An `IdentityProvider` seam structurally copied from `jotter app/Domain/Auth/Contracts/IdentityProvider.php`: `LocalIdentityProvider` (default) and a `GrandpaSsonIdentityProvider` that **composes** the local one and overrides only identity resolution. The interface **SHOULD** answer both "who is this" and "what may they see" (`accessibleTenantIds()` returning `null` for unrestricted), so no caller branches on auth mode.

TallyMark **MUST** implement the real HTTP code flow (§14.6.1):

1. Redirect to `GET {base_url}/login/{provider}` (or `/login/email`) with `client_id`, `redirect_uri`, `state`. Providers are limited to `google` | `microsoft` | `github`; `redirect_uri` must match the registered value **exactly**.
2. The broker returns `?code=…&state=…`. `state` **MUST** be verified against the session.
3. Redeem **server-side and immediately** at `POST {base_url}/session/exchange` (form-encoded: `code`, `client_id`, `client_secret`, `redirect_uri`, optional `tenant`). **Broker auth codes live 60 seconds and are single-use** — redemption **MUST NOT** be deferred to a later request or a queued job.
4. The response carries `subject{id,email,name,idp}`, `tenant{id,slug,role}`, `tenants[]`, `groups[]`, `scopes[]`.
5. Provision or link a local user keyed on `subject.email`; map tenancy onto TallyMark tenants.

- The exchange **MUST** use a **confidential** RP client; the broker unconditionally requires `client_secret` and rejects public/PKCE clients here.
- `groups` are **opaque tenant-scoped slugs**; the broker owns no per-workspace RBAC by explicit non-goal. Group → TallyMark role mapping is TallyMark's job and **MUST** be explicit and configurable.
- Platform-admin status **MUST** be a local decision on the mirrored user row, never taken from a broker claim.
- The path **MUST** fail closed on any broker error, timeout, or malformed response.

### 14.4 Inbound B — machine tokens

Machine callers present an opaque `gpat_live_…` token, validated at `POST {base_url}/oauth/introspect`, mirroring TaskConnect's two-middleware chain: `AuthenticateApiKeyOrSanctum` (native `Bearer tm_*` key → *if enabled* broker introspection → Sanctum/web → `401`) and an audience-enforcing middleware requiring `active` **and** the required scope **and** `aud` covering the site's tenant scope.

- The actor **MUST** use the **SHA-256 fingerprint of the token** as its identifier, never the raw token, so it is safe to log.
- `audienceIncludes()` **MUST** accept both the raw id and the `workspace/<id>` prefixed form — the broker's docs use the prefixed style and operators routinely configure the other.
- A denial **MUST** write an audit entry with reason, required scope, presented scopes, presented audiences, and the fingerprint — never the token.
- **Introspection caching is mandatory.** The broker rate-limits `/oauth/token` and `/oauth/introspect` to **60 requests/minute/IP**. Results **MUST** be cached by token fingerprint for `introspection_cache_seconds` (default 30), bounded by the token's `exp`. Docs **MUST** state the resulting revocation latency, since caching trades away the broker's authoritative-revocation property.

### 14.5 Broker-side provisioning

```bash
php cron/seed_oauth_client.php --client-id=tallymark --name="TallyMark" \
  --redirect-uri=https://analytics.example.com/auth/grandpasson/callback --secret='<long-random>'

php cron/admin.php client:create-service "TallyMark" \
  --scopes=analytics:read,analytics:write --aud=workspace/ten_…
```

`--aud` is a **fixed pin, not a default**: the broker only issues tokens whose `aud` equals the client's configured audience, and a client created without `--aud` can never obtain one. The service-client secret is printed **once**.

**Cross-repo dependency — a hard blocker for inbound mode.** GrandpaSSOn's scope vocabulary is a **static allowlist** in `app/Domain/ScopeVocabulary.php`; there is no dynamic registration, and `client:create-service` aborts on an unknown scope. `analytics:read` / `analytics:write` / `analytics:callback` **do not exist today**. An issue **MUST** be opened in `suporterfid/grandpasson`; `docs/architecture/grandpasson-cross-repo.md` **MUST** track each scope's status. Until they land, PR12 ships with flags off, tested against fakes. **v0 MUST NOT be blocked on another repository.**

### 14.6 Known traps (verified against broker source)

1. **Do not copy Jotter's adapter.** `jotter GrandpaSSOnIdentityProvider` calls **no broker HTTP endpoint at all** — it reads the broker's `sessions` and `users` tables via raw PDO, assuming both apps share one MySQL database and one cookie host. It therefore **never sees `tenant`, `tenants`, or `groups`**. Copy its *interface and decorator structure*; implement the *transport* as the HTTP flow of §14.3.
2. **Do not copy `taskconnect HttpIntrospectionClient` verbatim — it has a live defect.** It sends credentials via `->withBasicAuth(...)` and posts only `['token' => …]`. The broker reads `client_id`/`client_secret` from the **request body** and implements no HTTP Basic auth anywhere. Against the current broker this returns `401 invalid_client`, mapped to `active: false` — inbound auth silently fails closed and every token looks invalid. TallyMark **MUST** post `client_id`, `client_secret`, and `token` in the form body. A test **MUST** assert this. It **SHOULD** also be reported upstream.
3. **Introspection never returns a tenant.** The claim exists in the response shape but no broker code path populates it. Machine tokens carry no tenancy; narrowing is via `aud` only.
4. **`GET /session` carries no tenancy** — v0 identity fields only. Tenant claims come from `/session/exchange` or `GET /me/active-tenant`.
5. **`grant_type=authorization_code` returns no `aud` and no tenant/group claims** — another reason to use a confidential RP client plus `/session/exchange`.
6. **`/.well-known/jwks.json` returns `{"keys":[]}` on internal error**, indistinguishable from "JWT disabled". v0 **SHOULD NOT** depend on JWT verification; the opaque token plus introspection is the supported path.
7. **No OIDC discovery document, no `/userinfo`, no refresh tokens.** Do not reach for a generic OIDC library expecting discovery.
8. **No seeded dev fixtures.** A subject id exists only after a real login, so `tenant:add-member` cannot run until someone signs in once. Email OTP (`/login/email`) is the lowest-friction path to a first subject.

---

## 15. TaskConnect integration

Three integrations, each independently optional. TallyMark **MUST** be fully functional with all three unconfigured — requiring TaskConnect would violate §4.1.

### 15.1 Scheduled digest reports delegated to TaskConnect

TallyMark sends weekly/monthly digests itself via the host's SMTP for the simple case. When a `taskconnect` channel is configured, report delivery **MAY** instead be submitted as a TaskConnect task, reusing its retry, backoff, and DLQ machinery rather than reimplementing them.

- Submitted to `POST /v1/tenants/{tenantId}/environments/{environmentId}/tasks`.
- Authenticated with a TaskConnect API key, or — when `GRANDPASSON_OUTBOUND_ENABLED` is on — a broker machine token carrying `tasks:write` and an `aud` covering the target workspace.
- An idempotency key derived from `(report_id, period)` **MUST** be sent, so a retried tick cannot duplicate a report; TaskConnect enforces this via its `idempotency` middleware.
- A `2xx` means *accepted for delivery*, not delivered. The UI **MUST** show the TaskConnect run link and **MUST NOT** claim success.

### 15.2 Goal conversions as automation triggers

The most valuable direction, and a genuine capability multiplier: when a goal fires, TallyMark **MAY** submit a TaskConnect task, turning an analytics signal into server-side automation ("when a Signup conversion happens, call our CRM webhook") while inheriting TaskConnect's SSRF protection, retry policy, and dead-letter queue.

- Dispatch happens at **ingest**, never on the hot path.
- Conversions **MUST** be batched per tick — one task per goal per bucket carrying a count, **not** one task per conversion. A viral page would otherwise submit thousands of tasks and exhaust the TaskConnect workspace's submit rate limit.
- Payloads **MUST** carry aggregate data only — count, goal, period, site — never a visitor hash. Forwarding per-visitor signals to a third-party system would undo §8 in one step, and this **MUST** be enforced by a test, not only by convention.

### 15.3 Ingest heartbeat for external monitoring

`analytics:ingest` and `analytics:rollup` **MUST** write heartbeats. The installation **SHOULD** expose an authenticated health endpoint reporting freshness, buffer depth, and shed counts, so a sibling uptime monitor or a TaskConnect health task can alert when ingest dies.

The cheapest configuration-only version, worth documenting: chain a heartbeat ping after the ingest cron so a dead cron is detected externally.

```cron
* * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:ingest >/dev/null 2>&1 && curl -fsS -m 10 https://status.example.com/ping/<token> >/dev/null 2>&1
```

### 15.4 What is explicitly not integrated

- The collector **MUST NOT** call TaskConnect, or anything else, synchronously. Nothing on the hot path may depend on a network service.
- The two products **MUST NOT** share a database.
- No shared Composer package in v0. Duplicated outbound-policy code is accepted with source comments; extraction is a post-v0 candidate.

---

## 16. Development environment (Docker only)

### 16.1 The `tm` wrapper

Twin wrappers with identical verb lists (`scripts/tm.sh`, `scripts/tm.ps1`) plus a `Makefile` proxying to the bash one, mirroring TaskConnect's `tc`:

```text
up · down · bootstrap · composer · artisan · npm · test · e2e · load · release · deploy · shell · help
```

- The script **MUST** resolve the repo root from `BASH_SOURCE` and `cd` there.
- A single `compose()` helper **MUST** wrap `docker compose -f compose.yaml [-f compose.ci.yaml]`, the CI overlay auto-selected from `TM_CI=1` / `CI=true` / `GITHUB_ACTIONS=true`.
- `composer install` **MUST** retry 3 times with exponential backoff (5 s, 10 s).
- If `COMPOSER_PACKAGIST_URL` is set, warn on stderr and forward it. Mirror configuration **MUST NOT** be committed.
- `bootstrap` **MUST** be idempotent, probing for `artisan` / `package.json` / a test runner.
- **`load`** is TallyMark-specific: runs the §13.2 load fixture and prints throughput.

### 16.2 Compose services

| Service | Build | Purpose |
|---|---|---|
| `app` | `php:8.2-apache` | Application + collector, `${APP_PORT:-8060}:80` |
| `mysql` | `mysql:8.0` | Database, `mysqladmin ping` healthcheck |
| `mailpit` | `axllent/mailpit` | Digest report sink |
| `demosite` | tiny static nginx | A real page carrying the tracking snippet, for E2E |
| `node` | profile `dev` | Vite dev server |

`compose.ci.yaml` **MUST** override ports only, via Compose's `!override` tag.

### 16.3 Running alongside the sibling projects

Default ports collide across the family; TallyMark **MUST** publish on distinct defaults, every one env-overridable:

| Project | Ports taken |
|---|---|
| GrandpaSSOn | 8080, 8081, 3306 |
| TaskConnect | 8080, 8025, 8090, 3306 |
| **TallyMark (proposed)** | **8060** (app), **8045** (Mailpit), **8065** (demosite), **3309** (MySQL) |

GrandpaSSOn publishes no service alias and joins no external network, so cross-project HTTP **MUST** go via the host: set `GRANDPASSON_BASE_URL=http://host.docker.internal:8080` and add `extra_hosts: ["host.docker.internal:host-gateway"]` to `app`. `http://web:80` **MUST NOT** be assumed to resolve. The broker's `BROKER_BASE_URL` must be the URL the **browser** sees, since it becomes the JWT `iss` and drives URL-prefix derivation.

### 16.4 Local verification

No GitHub Actions CI, mirroring TaskConnect. Before pushing:

```bash
./scripts/tm.sh test
./scripts/tm.sh npm --prefix frontend run test
./scripts/tm.sh npm --prefix frontend run build
./scripts/tm.sh load          # throughput must not regress
```

---

## 17. Production deployment (cPanel / Hostinger)

### 17.1 Requirements

PHP 8.2+ with the §4.1 extension list; MySQL 8.0+; Apache/LiteSpeed with `mod_rewrite`, document root at `public/`; per-minute cron; writable `storage/` (including the buffer directory) and `bootstrap/cache/`. **Not required:** Node.js, Docker, Redis, ClickHouse, queue workers, or Composer (the release ships `vendor/`).

### 17.2 The collector must not be routed through Laravel

This is the single most important deployment detail, and the easiest to break.

- `public/px.php` is a real file, so Laravel's stock `public/.htaccess` (which rewrites only *non-existent* paths to `index.php`) serves it directly. This works by default — but it is fragile, and any future rewrite rule could silently route the collector through the framework, destroying its performance characteristics without any visible error.
- A test **MUST** assert that requesting `/px.php` does not boot the framework — for example by asserting the response carries no framework header and no session cookie, and by asserting the file itself contains no `vendor/autoload` reference.
- `docs/deployment/` **MUST** show the `.htaccess` for both layouts: document root at `public/`, and the Hostinger-style layout where the app lives inside `public_html` with a root `.htaccess` rewriting into `public/` (mirroring TaskConnect's injected rule, including the dotfile hard-deny).
- The buffer directory **MUST** be verified non-web-readable during installation; the acceptance checklist **MUST** include fetching it and confirming `403`/`404`.

### 17.3 Cron

```cron
* * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:ingest >/dev/null 2>&1
* * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:rollup >/dev/null 2>&1
41 * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:maintenance >/dev/null 2>&1
```

Docs **MUST** warn that Hostinger's default `php` is often 7.4 and that CLI PHP is configured separately from the website's PHP version in hPanel.

### 17.4 Release packaging

Mirror `taskconnect docker/release/Dockerfile` — release-as-a-Dockerfile: **vendor** (`composer install --no-dev --optimize-autoloader`), **frontend** (`npm ci && npm run build`), **release** (assemble, strip `node_modules tests .git .github docker scripts compose*.yaml Makefile phpunit.xml`, create runtime dirs, `chmod 775`, zip + `sha256sum`), **export** (`FROM scratch` → `dist/`).

The release **MUST** additionally contain `public/px.php` and `public/tm.js` at their exact production paths, and `validate-release.sh` **MUST** assert both are present — the product is inoperable without them, and their absence would not otherwise fail any structural check.

### 17.5 Release validation

`scripts/validate-release.sh` **MUST** run automatically at the end of `tm release` and fail on:

- a `.env` or `.env.*` other than `.env.example`; `*.pem`, `*.key`, `id_rsa`, `id_ed25519`, `*.p12`, `*.pfx`; a `BEGIN … PRIVATE KEY` block; credential-like assignments outside the placeholder allowlist; token-like literals (`sk_live_…`, `xox[baprs]-…`);
- missing `app/artisan`, `app/vendor/`, `app/public/build/manifest.json`, `app/public/px.php`, `app/public/tm.js`;
- present `node_modules` or `tests/`;
- **any `composer.lock` package that offers no permitted licence option**, or any dual-licensed package whose selected permissive option is not recorded in `docs/security/dependency-audit.md` (§4.4);
- **any vendored geo database file** (§10.5).

The validator **MUST** itself be covered by a feature test planting a `.env`, a `.pem`, and a GPL package entry, asserting non-zero exit — mirroring `taskconnect tests/Feature/ReleaseSecretScanTest.php`. **These checks MUST be preserved in every PR.**

### 17.6 Installation flow

1. Upload and extract the release; point the document root at `public/`.
2. Copy `.env.example` to `.env`; set `APP_URL`, `APP_KEY`, database credentials, mail.
3. Make `storage/` and `bootstrap/cache/` writable; confirm the buffer directory is **not** web-readable.
4. `php artisan migrate --force`
5. `php artisan platform:bootstrap-admin you@example.com 'StrongPassword' --name='You'`
6. Configure the three cron lines (§17.3).
7. Create a site, install the snippet, load the page, and confirm a pageview appears within ~2 minutes.
8. Verify `/px.php` responds `204` and sets no cookie.
9. Complete the security and privacy checklists.

`docs/deployment/` **MUST** contain small, single-purpose, cross-linked files mirroring TaskConnect's split: `requirements.md`, `installation.md`, `cron.md`, `upgrade.md`, `backup.md`, `security.md`, `troubleshooting.md`, `acceptance-checklist.md`, `automated-ftp-deploy.md`.

---

## 18. The tracking script

- Hand-written vanilla JS, **< 1 KB gzipped**, no framework, no build-time dependency beyond minification. A test **MUST** assert the size ceiling so it cannot drift.
- Served from `public/tm.js` with a long `Cache-Control` and a content hash in the filename for cache busting.
- **MUST** use `navigator.sendBeacon` when available, falling back to `fetch(..., {keepalive:true})`, then to an image GET.
- **MUST NOT** read or write cookies, `localStorage`, or `sessionStorage`, and **MUST NOT** perform fingerprinting (no canvas, WebGL, font enumeration, or hardware probing). A test **MUST** assert the absence of these APIs in the built file.
- **MUST** honour `navigator.doNotTrack` / `globalPrivacyControl` when the site is configured to respect them.
- **MUST NOT** run on `localhost` or private hostnames unless explicitly enabled, so development traffic does not pollute production data.
- **SHOULD** support SPA route changes by hooking `history.pushState`/`replaceState` and `popstate`, as an opt-in script variant.
- **MUST** expose `window.tallymark('event', name, props)` for custom events and goals.
- **MUST** fail completely silently — a broken analytics script **MUST NOT** produce console errors or affect the host page.
- The snippet **MUST** be a single `<script defer>` tag with `data-site` and no inline configuration object.

---

## 19. Frontend, i18n, accessibility

- **Screens:** login, site list, site dashboard (with period picker and comparison), pages, referrers, countries, devices, campaigns, goals, real-time-ish "last 30 minutes", settings, shared dashboards, members, API keys, audit log.
- **i18n:** `en` and `pt-BR` **MUST** both be complete at v0. SPA namespaces under `frontend/src/i18n/`; Blade shared dashboards use `lang/{en,pt_BR}/`.
- Numbers, percentages, dates, and durations **MUST** be locale-formatted and rendered in the site's configured timezone, with the timezone shown.
- **Accessibility:** WCAG 2.2 AA. Charts **MUST** have an accessible tabular equivalent; trends **MUST NOT** be conveyed by colour alone. An automated a11y sweep **SHOULD** run in Playwright, mirroring TaskConnect's `a11y.spec.ts`.

---

## 20. First implementation plan — PR sequence

One PR unit at a time. Each **MUST** leave `main` green with `tm test` passing, and **MUST** update `STATUS.md`.

| PR | Title | Deliverable | Definition of done |
|---|---|---|---|
| **PR0** | Repo scaffold & Docker loop | Laravel 12 skeleton, `compose.yaml`/`compose.ci.yaml`, `docker/{php,node,demosite}`, `scripts/tm.{sh,ps1}`, `Makefile`, `phpunit.xml` (SQLite `:memory:`), MIT `LICENSE`, `README.md`, `AGENTS.md`, `CLAUDE.md`, `.cursor/rules/`, `STATUS.md`, `BACKLOG.md` | `tm up` serves the app; `tm test` runs; `CLAUDE.md` names the LGPL device-detector prohibition |
| **PR1** | Tenancy, auth, isolation | users/tenants/memberships/audit_logs migrations, ULID `public_id`, local auth, policies, `tenant.context`, `platform:bootstrap-admin` | Tenant-isolation feature tests pass; cross-tenant access returns 404/403 |
| **PR2** | Sites & the site map file | `sites`/`site_hosts` CRUD, `site_key` generation + rotation, atomic `tm-sites.php` generation | Map file regenerates on every mutation; written atomically; rotation preserves history |
| **PR3** | **The collector** | `public/px.php` standalone, buffer sharding, prefilters, URL sanitization, host validation, caps | **Asserts no `vendor/autoload`, no PDO symbol, no `Set-Cookie`, always `204`.** Unknown key dropped. Buffer not web-readable. p99 < 5 ms measured |
| **PR4** | Visitor hashing & salts | `VisitorHasher`, `salts` table, daily rotation + grace + destruction, stale-salt alarm | Hash changes across rotation; old salt provably destroyed; no IP reaches disk (asserted) |
| **PR5** | Ingest pipeline | `analytics:ingest`, closed-bucket rule, `ingest_batches` claim leases, `TickBudget`, malformed-line tolerance | Overlapping cron runs provably never double-count; re-running a batch is a no-op; budget exits cleanly |
| **PR6** | Sessionization & classification | `Sessionizer`, `crawler-detect` (MIT), internal device classifier, referrer normalization | Pure unit tests, zero I/O; bots excluded and counted separately; licence audit green |
| **PR7** | Aggregation & cardinality | `stats_hourly_*` with unique keys, idempotent UPSERTs, `CardinalityGuard`, `(other)` folding | Double-ingest produces identical numbers; cap overflow folds and warns |
| **PR8** | Rollups & retention | `analytics:rollup`, `analytics:maintenance`, `stats_daily_*`, `daily_visitors`, chunked retention gated on rollup | Exact daily distinct visitors; hourly never deleted before daily exists |
| **PR9** | Dashboard API & SPA | Reporting endpoints, Vue 3 SPA, all §19 screens, `en` + `pt-BR` complete | Bounded query count asserted; approximations labelled in UI; `vue-tsc` clean |
| **PR10** | Tracking script | `public/tm.js`, sendBeacon + fallbacks, SPA variant, custom events | **< 1 KB gzipped asserted**; no cookie/storage/fingerprinting APIs asserted; silent failure |
| **PR11** | Goals & shared dashboards | Goals, conversion metrics, public Blade dashboards with caching | Shared dashboard leaks no `site_key`, hostname, or operator identity (asserted) |
| **PR12** | GrandpaSSOn seam | `config/grandpasson.php`, `IdentityProvider` seam via **HTTP code flow**, `/session/exchange`, introspection **with body credentials** + cache, `docs/integrations/grandpasson.md`, cross-repo scope issue | Dual-mode: all green with flags off. **Test asserts introspection posts credentials in the form body** (§14.6.2). `audienceIncludes` tested both forms. `state` mismatch and expired code fail closed |
| **PR13** | TaskConnect integration | Digest delegation, batched goal-conversion tasks, idempotency keys, health endpoint, `docs/integrations/taskconnect.md` | **Test asserts no visitor hash ever leaves in a payload** (§15.2); works fully unconfigured; degrades gracefully |
| **PR14** | Release, deploy, licence audit | `docker/release/Dockerfile`, `validate-release.sh` + feature test, licence audit, `docker/deploy/`, `scripts/deploy.sh`, all `docs/deployment/` | `tm release` produces a validated zip; planted `.env`, `.pem`, and GPL dependency each fail the build |
| **PR15** | Load fixture & capacity | Synthetic buffer generator, `tm load`, documented throughput | §13.1 targets met and recorded; regression baseline committed |
| **PR16** | E2E, a11y, privacy docs | Playwright (demosite → collector → ingest → dashboard), a11y sweep, `docs/privacy.md`, acceptance checklist | Suite green against compose; privacy doc states properties without legal overclaim |

### 20.1 Sequencing notes

- **PR3 and PR4 before PR5.** The buffer format and the visitor hash are the contract everything downstream depends on; changing them later invalidates stored data.
- **PR7 before PR8.** Rollups read aggregates; building them against a schema without unique keys would bake in double-counting.
- **PR6 gates the licence audit.** The first third-party classification dependency is where the LGPL mistake would enter; the audit **MUST** be wired in the same PR that introduces the dependency, not deferred to PR14.
- **PR12 and PR13 are independent** of each other and of PR9–PR11, and may be reordered if the cross-repo scope dependency (§14.5) blocks.

---

## 21. v0 stop line (Definition of Done)

- [ ] A fresh clone reaches a working app with `./scripts/tm.sh up` and no host PHP/Node/Composer.
- [ ] `tm test`, `npm run test`, and `npm run build` are green.
- [ ] The collector demonstrably boots no framework, opens no database connection, and never sets a cookie.
- [ ] Collector p99 < 5 ms and the tracking script < 1 KB gzipped, both asserted by tests.
- [ ] 1,000,000 events ingest within budget on the reference profile, demonstrated by `tm load`.
- [ ] Two concurrent `analytics:ingest` runs provably never double-count.
- [ ] No IP address or raw User-Agent can be found anywhere in the buffer, database, or logs.
- [ ] Salt rotation destroys the previous salt and yesterday's hashes are provably unlinkable.
- [ ] Cardinality caps hold under a synthetic high-cardinality path flood, with visible `(other)` folding.
- [ ] `composer.lock` contains no GPL/LGPL/AGPL/SSPL dependency, enforced by the release build.
- [ ] No geo database is vendored in the repository or the release zip.
- [ ] Both `en` and `pt-BR` are complete in the SPA and shared dashboards.
- [ ] `tm release` produces a zip passing `validate-release.sh`, including secret and licence scans.
- [ ] A real cPanel/Hostinger deployment is documented as verified in `docs/deployment/acceptance-checklist.md`, including that `/px.php` is not routed through Laravel.
- [ ] GrandpaSSOn dual-mode works with flags off (default) and against a live broker.
- [ ] `docs/privacy.md` states collection properties without an unqualified compliance claim.
- [ ] Every merged PR is linked to a closed GitHub issue (§4.3).

### 21.1 Post-v0 candidates

Funnels; limited cross-dimension filtering via a narrow pre-computed combination table; a first-party proxy endpoint so the collector can be served from the tracked site's own domain (ad-blocker resilience); server-side event ingestion API; import from Matomo/Plausible/GA4; WordPress and Laravel snippet plugins; extraction of the shared outbound-policy code into a Composer package; an MCP server exposing site metrics to agents (mirroring Jotter).

---

## 22. Open questions (resolve before or during implementation)

1. **Buffer vs. direct UPSERT.** This spec commits to file buffering (§7.2) for a no-DB hot path. The alternative — `INSERT … ON DUPLICATE KEY UPDATE` straight from the collector — is simpler and has no data-loss window, at the cost of a DB connection and ~5 round trips per pageview. Recommendation: keep the buffer, but **build the load fixture (PR15) early enough to validate the assumption**, and record the measured comparison in `docs/architecture/`.
2. **Visitor counting exactness.** §10.3 specifies exact daily distinct counts via `daily_visitors`, which costs one row per visitor per day per site. At 100k daily visitors that is 100k rows/day. Is that acceptable, or should the dashboard report approximate visitors and drop the table? Recommendation: keep exactness at day granularity, cap the table with retention, and revisit if the load fixture shows it dominating growth.
3. **Sampling policy.** Should high-traffic sites sample by default above a threshold, or only when the operator opts in? Sampling silently changes what every number means. Recommendation: opt-in only, and label sampled figures everywhere they appear.
4. **First-party proxy.** Serving the collector from the tracked site's own domain defeats most ad-blockers but requires the operator to deploy a small proxy file. Is that in v0 scope or post-v0? Recommendation: post-v0, but keep the collector's contract stable enough that a proxy is trivial.
5. **Geo on the hot path.** §10.5 prefers edge headers precisely because a database lookup in the collector would require autoloading. Is a header-only default acceptable for the target audience, given many are not behind Cloudflare and will simply see `unknown`? Recommendation: yes for v0, with a clearly documented opt-in path.
6. **GrandpaSSOn scope vocabulary.** `analytics:read` / `analytics:write` / `analytics:callback` must be added to the broker (§14.5). Who owns that issue, and does inbound mode ship disabled in v0 until it lands?
7. **Shared-hosting inode limits.** The buffer creates up to `shards × 1440` files/day before cleanup. Some hosts enforce tight inode quotas. Should the shard count adapt, or should ingest run more aggressively? Recommendation: document the arithmetic, default to 4 shards, and have `analytics:maintenance` warn on orphan accumulation.

---

## 23. Repository layout (target)

```text
tallymark/
  app/
    Application/{Ingest,Rollups,Sites,Goals,Reports,Members,ApiKeys,Audit,
                 Tenancy,Auth,Notifications,GrandpaSson}/
    Domain/{Collection,Identity,Classification,Aggregation,Reporting,Outbound,Shared}/
    Infrastructure/{Persistence/Eloquent,HttpClient,Geo}/
    Http/{Controllers/Api/V1,Controllers/Public,Resources,Middleware,Support}/
    Console/Commands/
    Policies/  Providers/
  bootstrap/  config/  database/{factories,migrations,seeders}/
  frontend/src/{components,pages,router,stores,services,i18n,utils}/  frontend/e2e/
  lang/{en,pt_BR}/
  public/
    px.php            # THE COLLECTOR — standalone, zero dependencies
    tm.js             # tracking script
    index.php         # Laravel front controller
  resources/views/shared/          # Blade shared dashboards
  routes/{api.php,web.php}
  scripts/{tm.sh,tm.ps1,deploy.sh,validate-release.sh,license-audit.sh}
  docker/{php,node,demosite,release,deploy}/
  storage/tm-buffer/               # not web-readable
  tests/{Unit,Feature,Collector,Support}/
  docs/
    tallymark-initial-spec-and-build-plan.md   # this document
    privacy.md
    architecture/  deployment/  security/dependency-audit.md
    integrations/{taskconnect.md,grandpasson.md}
  compose.yaml  compose.ci.yaml  Makefile  phpunit.xml
  AGENTS.md  CLAUDE.md  README.md  STATUS.md  BACKLOG.md  CHANGELOG.md  LICENSE
  .cursor/rules/*.mdc
```

`AGENTS.md` **SHOULD** be a ~30-line index deferring to `CLAUDE.md`; `.cursor/rules/` **SHOULD** hold exactly one `alwaysApply: true` hard-constraints rule (naming the MIT-only and no-framework-in-collector rules) plus file-scoped rules — mirroring TaskConnect.

---

## 24. Testing strategy

### 24.1 Unit (no I/O)

`EventLine` round-trip (both collector and application implementations); `VisitorHasher` including rotation boundaries; `Sessionizer` including the midnight split; bot and device classification; referrer normalization; `CardinalityGuard` folding; metric calculation; `BucketMath` timezone conversion; `IpClassifier` and `UrlValidator`.

### 24.2 Collector tests (`tests/Collector/`) — a distinct suite

The collector cannot be tested through Laravel's HTTP kernel, since booting it would defeat the purpose. This suite **MUST** invoke `px.php` as an isolated process or via a bare PHP built-in server and assert:

no `vendor/autoload` reference in the file; no PDO/mysqli symbol reachable; always `204`; never a `Set-Cookie`; unknown key dropped; oversized body abandoned; DNT honoured; query strings stripped to the allowlist; buffer line contains no IP and no raw User-Agent; buffer directory not web-readable; measured p99 under the ceiling.

### 24.3 Feature (SQLite `:memory:`)

Tenant isolation on every resource; ingest claim-lease concurrency and exactly-once semantics; idempotent aggregation under double-ingest; retention gating; cardinality folding; shared-dashboard disclosure; dashboard query count; GrandpaSSOn inbound with a fake introspection client; release secret **and licence** scans.

### 24.4 Test doubles (`tests/Support/`)

Mirror TaskConnect's support layer: `FixedClock`, `ArrayDnsResolver`, `MockPinnedHttpTransport`, `FakeGrandpaSsonIntrospectionClient`, `FakeGrandpaSsonTokenClient`, `CreatesTenantFixtures`, `CreatesSiteFixtures`, `GeneratesBufferFixtures`.

### 24.5 Portability

Tests run on SQLite; production on MySQL. Migrations and queries **MUST** be portable, including the driver-detected `SKIP LOCKED` branch and the `ON DUPLICATE KEY UPDATE` / `INSERT … ON CONFLICT` split. Any MySQL-only construct **MUST** have an explicit SQLite fallback and a comment saying why.

### 24.6 End-to-end (Playwright)

Load the `demosite` page carrying a real snippet → assert the beacon fired → run `analytics:ingest` → assert the pageview appears on the dashboard. Plus a shared-dashboard journey and an accessibility sweep. Browsers **MUST** be installed inside the node container, never on the host.

---

## 25. Licence

MIT. See `LICENSE`.

Patterns are adapted from [`suporterfid/taskconnect`](https://github.com/suporterfid/taskconnect), [`suporterfid/grandpasson`](https://github.com/suporterfid/grandpasson), and [`suporterfid/convertdoctomd-php`](https://github.com/suporterfid/convertdoctomd-php) — all MIT and under the same ownership.

All runtime dependencies are permissively licensed by construction (§4.4), enforced by the release build. This is the property that distinguishes TallyMark from every existing PHP + MySQL analytics platform, and it **MUST NOT** be traded away for any feature.
