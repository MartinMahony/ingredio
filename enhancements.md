# Ingredio — Enhancements & Hardening Backlog

> Outcome of the full project review (performance, security, usability). Work through the waves top-to-bottom; each wave should end green (tests pass, `vendor/bin/pint --dirty` clean) before moving on.
>
> Severity: **P0** = should do before launch / next deploy, **P1** = important, **P2** = nice-to-have.

---

## Wave 1 — P0: Reliability & Security Quick Wins

### Performance / reliability
- [x] **Add a worker-safe `timeout` to `ProcessRecipeScan`** and make `retry_after` exceed it.
  - Added `public int $timeout = 90;` to the job.
  - Set `DB_QUEUE_RETRY_AFTER=180` / `REDIS_QUEUE_RETRY_AFTER=180` defaults in `config/queue.php` and `.env.example`.
  - Updated `scope.md` queue-worker command to `php artisan queue:work --tries=3 --backoff=10 --timeout=120 --sleep=1` (use `--sleep=1` with the DB driver to reduce pickup latency).
- [x] **Add the missing composite database indexes** for the common query patterns.
  - Migration `2026_08_07_175109_add_composite_indexes_for_dashboard_and_relations.php` adds indexes on `recipes`, `ingredients`, `recipe_steps`, `recipe_tag`, and `recipe_scans`.
- [x] **Lower and/or visibility-gate the scan-status polling interval**.
  - Updated `scans/show.blade.php` to `wire:poll.1s.visible="poll"`.

### Security
- [x] **Rate-limit login attempts**.
  - Added a per-email+IP `RateLimiter` in `auth/login.blade.php` (5 attempts per minute), with tests.
- [x] **Tighten `description`/`notes` and array validation** on the recipe form.
  - Added `max:10000` to `description` and `notes`, and `max:200` to `ingredients`/`steps` arrays; tests added.

---

## Wave 2 — P1: Extraction Performance & Queue

> Goal: shrink the 9–12 s scan time, which is dominated by the synchronous Gemini round-trip plus inline base64 payload upload.

- [x] **Cap the extracted URL text length before it reaches the LLM**.
  - `ScanSource::fromText()` now truncates text to 20 000 characters (`…`).
  - File: `app/Extraction/Data/ScanSource.php`.
- [x] **Stream/abort large URL fetches**.
  - `UrlContentFetcher::readBody()` streams the response and aborts as soon as `max_bytes` is exceeded, without loading the whole body first.
  - File: `app/Extraction/Support/UrlContentFetcher.php`.
- [x] **Set a `connect_timeout` separate from the total `timeout`** on the URL fetch call.
  - Added `connect_timeout` to `UrlContentFetcher`, `config/scanning.php`, `.env.example` (`SCAN_URL_CONNECT_TIMEOUT=5`).
  - File: `app/Extraction/Support/UrlContentFetcher.php`.
- [x] **Add `max_output_tokens` to the Gemini payload** to cap runaway responses.
  - `GeminiRecipeExtractor` now sends `maxOutputTokens` (default 2048) from `config/scanning.gemini.max_output_tokens` / `GEMINI_MAX_OUTPUT_TOKENS`.
  - File: `app/Extraction/Drivers/GeminiRecipeExtractor.php`.
- [x] **Add timing logs around the LLM call and URL fetch** so future optimisation is data-driven.
  - Files: `app/Extraction/Drivers/GeminiRecipeExtractor.php`, `app/Extraction/Support/UrlContentFetcher.php`.
- [x] **Use `queue:work` instead of `queue:listen` in local dev** with `--sleep=1` to remove the 3 s queue-pickup delay.
  - File: `composer.json`.
- [x] **Fix `Collection::recipes()` ordering** to use the pivot `created_at`.
  - File: `app/Models/Collection.php`.

---

## Wave 3 — P1: Security Hardening

- [x] **Rate-limit or throttle registration** to prevent unlimited account creation and scan-quota bypass.
  - `auth.register` now rate-limits by IP to 3 registrations per hour using `RateLimiter`.
  - File: `resources/views/livewire/auth/register.blade.php`.
- [x] **Re-enable email verification** (`MustVerifyEmail`) since the app is not invite-only.
  - `User` now implements `MustVerifyEmail`; added `verification.notice`, `verification.verify`, and `verification.send` routes and a notice view.
  - `Registered` event triggers `SendEmailVerificationNotification`; protected routes now require `verified` middleware.
  - Files: `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php`, `resources/views/livewire/auth/verify-email.blade.php`.
- [x] **Restrict `trustProxies` in production** to the actual proxy CIDRs instead of `*`.
  - `bootstrap/app.php` reads `TRUSTED_PROXIES` from `.env` (defaults to `*` only when unset) and trusts only `X-Forwarded-For/Port/Proto` — `X-Forwarded-Host` is deliberately excluded to prevent host-header spoofing of generated URLs.
  - File: `bootstrap/app.php` / production `.env`.
- [x] **Add production safety notes to `.env.example`** for `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and `SESSION_ENCRYPT=true`.
- [x] **Harden SSRF protection against DNS rebinding** (defence-in-depth).
  - `UrlSafetyValidator::ensureSafe()` now returns the validated public IPs; `UrlContentFetcher` pins the hostname to the first safe IP via `CURLOPT_RESOLVE` so the connection cannot be redirected by a later DNS response.
  - File: `app/Extraction/Support/UrlSafetyValidator.php`, `app/Extraction/Support/UrlContentFetcher.php`.

---

## Wave 4 — P2: Usability & Polish

- [x] **Add a dashboard sort control** (newest / oldest / title A–Z).
  - Added a `sort` selector and URL-synced `search`, `cuisine`, `tag`, and `sort` query params.
  - File: `resources/views/livewire/dashboard.blade.php`.
- [x] **Add a "Duplicate recipe" action** on `recipes.show`.
  - Uses `replicate()` and copies ingredients, steps, and tags, then redirects to the copy for editing.
  - File: `resources/views/livewire/recipes/show.blade.php`.
- [x] **Add cooking-mode checklists** on `recipes.show`.
  - "Cook mode" toggle shows checkboxes for ingredients and steps and tracks checked state purely in Alpine.
  - File: `resources/views/livewire/recipes/show.blade.php`.
- [x] **Standardise `wire:loading` states** on all mutating buttons (save, create collection, share controls, etc.).
  - `wire:loading.attr="disabled"` and loading text added to dashboard, recipe, collection, scan, and auth action buttons.
- [x] **Dirty-check the recipe form before the "Cancel" link navigates away**.
  - `recipes.manage` now uses Alpine `dirty` state, `beforeunload` warning, and a confirmation prompt before `Livewire.navigate`.
  - File: `resources/views/livewire/recipes/manage.blade.php`.
- [x] **Make tag and cuisine pills clickable** — link back to the dashboard filtered by that tag/cuisine.
  - File: `resources/views/livewire/recipes/show.blade.php`.
- [x] **Add collection rename/edit** (the `CollectionPolicy::update` exists but no UI uses it).
  - Inline edit form on `collections.index` with uniqueness validation scoped to the user.
  - File: `resources/views/livewire/collections/index.blade.php`.
- [x] **Eager-load relations in `recipes.manage` mount** to avoid three lazy queries on edit.
  - File: `resources/views/livewire/recipes/manage.blade.php`.
- [x] **Show `steps_count` on dashboard cards or drop the unused `withCount('steps')`**.
  - Dashboard cards now show ingredient and step counts.
  - File: `resources/views/livewire/dashboard.blade.php`.

---

## Wave 5 — P2: Advanced Features (later)

- [ ] **Bulk actions on the dashboard**: select multiple recipes → add to collection / delete.
- [ ] **Favorites / "cooked" tracking**: `is_favorite` boolean or `last_cooked_at` timestamp to help rediscovery.
- [ ] **Tag autocomplete** using a native `<datalist>` of existing tags to reduce fragmentation ("soup" vs "soups").
- [ ] **Recent scans list / scan history** so failed scans are easy to find again.
- [ ] **Recipe preview modal** from the dashboard to quickly glance at ingredients without opening the full page.

---

## Notes

- Items explicitly dropped in `scope.md` (export, ingredient auto-scaling, source retention) are not re-proposed here.
- For any change to the Gemini integration, check the installed `google-gemini-php/laravel` / `gemini-api-php` package docs or Boost `search-docs` first, since the app currently talks to the REST endpoint directly.
