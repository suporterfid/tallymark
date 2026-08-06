# TallyMark i18n backend completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Persist user locale server-side, set app locale per-request, wire the existing client-only language switcher to load/save it.

**Tech Stack:** Laravel 12, PHPUnit, Vue 3 (single `App.vue`, no router/store yet). Locale identifier convention: `en` / `pt-BR` (hyphen, matching the shipped frontend).

## Global Constraints
- Use only `./scripts/tm.sh` for composer/npm/artisan/test commands.
- Supported locales: `en`, `pt-BR`.
- No GrandpaSSOn work — out of scope (not yet built in this repo).

---

### Task 1: `locale` column + `SetLocaleFromUser` middleware

**Files:**
- Create: `database/migrations/<timestamp>_add_locale_to_users_table.php`
- Modify: `app/Infrastructure/Persistence/Eloquent/User.php` (fillable + default attribute)
- Create: `app/Http/Middleware/SetLocaleFromUser.php`
- Modify: `bootstrap/app.php` (register alias)
- Modify: `routes/api.php` (attach to the `auth` route group)
- Test: `tests/Feature/SetLocaleFromUserTest.php` (new)

- [ ] Write failing test: authenticated user with `locale = 'pt-BR'` hitting `PATCH /api/v1/me/locale` with an invalid value gets a Portuguese-flavored 422 (or, simpler and independent of Task 3's lang files: directly assert `app()->getLocale() === 'pt-BR'` mid-request by having the test route/controller expose it — prefer testing through `GET /api/v1/me` once Task 2 exists, reordering if needed).
- [ ] Migration: `$table->string('locale', 10)->default('en')->after('email_verified_at');` (adjust column position to whatever fits `users`' actual column order).
- [ ] Add `'locale'` to `User::$fillable`; add `protected $attributes = ['locale' => 'en'];` so in-memory/factory-created models reflect the DB default without a round-trip (same fix jotter needed).
- [ ] Middleware (mirrors jotter/taskconnect/statusconnect's):
  ```php
  <?php

  namespace App\Http\Middleware;

  use App\Infrastructure\Persistence\Eloquent\User;
  use Closure;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\App;
  use Symfony\Component\HttpFoundation\Response;

  class SetLocaleFromUser
  {
      public function handle(Request $request, Closure $next): Response
      {
          $user = $request->user();
          $locale = $user instanceof User ? $user->locale : config('app.locale');
          App::setLocale($locale);

          return $next($request);
      }
  }
  ```
- [ ] Register `'locale.from_user' => \App\Http\Middleware\SetLocaleFromUser::class` alias in `bootstrap/app.php`.
- [ ] Add `'locale.from_user'` to the `Route::middleware(['auth', 'tenant.context'])->group(...)` array in `routes/api.php` — check whether `/me`-style routes belong inside or outside the `tenant.context` group (they don't need a tenant, so may need their own `Route::middleware(['auth', 'locale.from_user'])->group(...)` block instead).
- [ ] Run test, verify pass (after Task 2's endpoints exist — implement Tasks 1+2 together if the test needs both).
- [ ] Commit: `feat(i18n): add locale column and SetLocaleFromUser middleware`

### Task 2: `GET /api/v1/me` and `PATCH /api/v1/me/locale`

**Files:**
- Create: `app/Http/Controllers/Api/V1/MeController.php`
- Create: `app/Http/Controllers/Api/V1/UserLocaleController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/UserLocaleTest.php` (new)

- [ ] Write failing test: `GET /api/v1/me` for an authenticated user returns `data.locale`.
- [ ] Write failing test: `PATCH /api/v1/me/locale` with `{"locale": "pt-BR"}` returns 200 and persists it; `{"locale": "fr"}` returns 422.
- [ ] `MeController::__invoke()`: `return response()->json(['data' => ['locale' => $request->user()->locale]]);`
- [ ] `UserLocaleController::__invoke()`: validate `locale` ∈ `['en', 'pt-BR']`, update `$request->user()->update(['locale' => $validated['locale']])`, return `['data' => ['locale' => $validated['locale']]]`.
- [ ] Add both routes inside an authenticated group in `routes/api.php` (with `locale.from_user` attached per Task 1).
- [ ] Run tests, verify pass; go back and verify Task 1's test passes too.
- [ ] Commit: `feat(i18n): add GET /me and PATCH /me/locale endpoints`

### Task 3: `lang/` scaffolding

**Files:**
- Create: `lang/en/auth.php`, `lang/en/validation.php` (`php artisan lang:publish`)
- Create: `lang/pt-BR/auth.php`, `lang/pt-BR/validation.php` (hand-translate)

- [ ] Run `./scripts/tm.sh artisan lang:publish`; fix ownership if root-owned.
- [ ] Hand-translate `lang/en/auth.php` → `lang/pt-BR/auth.php` (small file — `failed`, `password`, `throttle` keys).
- [ ] Copy/adapt `lang/pt-BR/validation.php` from jotter's or taskconnect's existing translation (`lang/pt-BR/validation.php`, same key set, locale-name-agnostic content) into this repo's `lang/pt-BR/` directory.
- [ ] Add a feature test asserting a `pt-BR` user gets `__('auth.failed')`'s Portuguese text on a failed login attempt if the acting user can be authenticated as `pt-BR` before the attempt fails (check feasibility — `SessionController::store()` runs before any session exists, so `SetLocaleFromUser` won't have fired; if there's no way to set locale pre-auth, skip this test and note in the commit message that this string only takes effect for already-authenticated locale-aware flows, consistent with jotter/taskconnect precedent for pre-auth strings).
- [ ] Commit: `feat(i18n): scaffold lang/ with auth and validation catalogs`

### Task 4: Wire the frontend language switcher to persist

**Files:**
- Modify: `frontend/src/App.vue`
- Test: check for an existing `App.spec.ts`/similar; if none exists, add a minimal one asserting the locale-change handler calls `fetch('/api/v1/me/locale', ...)` with a `PATCH` method (or skip a new test file if the project has no frontend test runner configured yet — verify via `frontend/package.json`'s scripts before deciding).

- [ ] On `App.vue` mount (`onMounted` — add the import), `fetch('/api/v1/me')`, and if the response is ok, set `locale.value` from `data.locale`; swallow errors (stay on the `navigator.language` default when unauthenticated).
- [ ] Change the language `<select>` to also persist on change: add an `onLocaleChange` handler that sets `locale.value` (as `v-model` already does) and fires `fetch('/api/v1/me/locale', { method: 'PATCH', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ locale: value }) })`, ignoring failures.
- [ ] Run `./scripts/tm.sh npm --prefix frontend run build` (and `run test` if a test script exists) to verify no regressions.
- [ ] Commit: `feat(i18n): persist the language switcher via PATCH /api/v1/me/locale`

### Final verification
- [ ] `./scripts/tm.sh test` — full backend suite green.
- [ ] `./scripts/tm.sh npm --prefix frontend run build` — clean.
- [ ] Invoke `superpowers:finishing-a-development-branch`.
