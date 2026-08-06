# TallyMark i18n backend completion (design)

**Goal:** persist the user's locale server-side, set the Laravel app locale per-request from it, and wire the existing (client-only) language switcher to load/save it — no GrandpaSSOn integration exists yet (planned separately, not part of this work).

## Context

TallyMark's frontend (`frontend/src/App.vue`) already has a working `vue-i18n` setup with complete `en`/`pt-BR` message catalogs (`frontend/src/i18n/index.ts`) and a language `<select>` bound directly to `locale.value` — but it's entirely client-side: it defaults from `navigator.language`, and switching it is never persisted anywhere, so it resets on every reload. There is no `/me` endpoint, no locale column, and no `lang/` directory on the backend at all. Authentication is local email/password (`LocalAuthenticator` via `SessionController`) — GrandpaSSOn SSO is a future PR, not yet built, so there's no external locale claim to consume here (unlike jotter/statusconnect).

The frontend already uses the hyphenated `pt-BR` locale key (`i18n/index.ts`, `App.vue`'s `<option value="pt-BR">`). CLAUDE.md's mention of `lang/{en,pt_BR}` is a generic aside, not an established convention anywhere in code — stick with `pt-BR` throughout for consistency with what's already shipped.

## Design

1. **`locale` column on `users`** (`VARCHAR(10) DEFAULT 'en'`) — no separate preferences table exists yet, so add it directly to `users`, matching jotter's simpler pattern.
2. **`SetLocaleFromUser` middleware**, registered on the authenticated (`auth`) route group, sets `App::setLocale($request->user()?->locale ?? config('app.locale'))`.
3. **`GET /api/v1/me`** — new endpoint returning `{ data: { locale } }` (minimal; nothing else is needed yet).
4. **`PATCH /api/v1/me/locale`** — new endpoint, validates `locale` ∈ `['en', 'pt-BR']`, updates `$user->locale`, returns the updated value.
5. **`App.vue` wiring**: on mount, `fetch('/api/v1/me')` and set `locale.value` from the response (falling back to the existing `navigator.language` default if the request fails, e.g. logged out); the language `<select>`'s `@change` calls `PATCH /api/v1/me/locale` (fire-and-forget, local `locale.value` already updated by `v-model`).
6. **`lang/en/auth.php` + `lang/pt-BR/auth.php`** (`php artisan lang:publish` then hand-translate) — `SessionController` already calls `__('auth.failed')`, but no lang files exist yet so it always renders Laravel's English default regardless of locale.
7. **`lang/en/validation.php` + `lang/pt-BR/validation.php`** — publish + translate, for any future validated endpoint (including the new `PATCH /api/v1/me/locale`).

## Out of scope

- GrandpaSSOn SSO integration (separate future PR; not built yet).
- No other hardcoded backend message strings were found to localize.

## Testing

- Feature: `SetLocaleFromUser` — authenticated user with `locale = 'pt-BR'` gets a Portuguese validation error from the new `PATCH /me/locale` endpoint's own invalid-value case; a user with no locale set defaults to English.
- Feature: `GET /me` returns the saved locale; `PATCH /me/locale` persists a valid value and rejects an unsupported one.
- Feature: `SessionController`'s `__('auth.failed')` message renders in the acting user's locale (only meaningful once already authenticated in some other request context, or via a direct `Lang::get()` assertion in the correct locale — check feasibility during implementation; a session-based login failure has no locale yet since the user isn't authenticated, so this may simply confirm the English default, matching jotter/taskconnect's ForgotPasswordController precedent).
