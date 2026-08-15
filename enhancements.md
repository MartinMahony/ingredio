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

## Wave 5 — P2: Advanced Features

- [x] **Bulk actions on the dashboard**: select multiple recipes → add to collection / delete.
  - Added checkboxes on each card, a bulk toolbar with select all / add to collection / delete selected, and `wire:loading` states.
  - File: `resources/views/livewire/dashboard.blade.php`.
- [x] **Favorites / "cooked" tracking**: `is_favorite` boolean or `last_cooked_at` timestamp to help rediscovery.
  - Added `is_favorite` and `last_cooked_at` columns to `recipes`, Favorite/Mark cooked actions on `recipes.show`, favorite sort/recently-cooked sort, and favorite/last-cooked indicators on cards.
  - File: `app/Models/Recipe.php`, `resources/views/livewire/recipes/show.blade.php`, `resources/views/livewire/dashboard.blade.php`.
- [x] **Tag autocomplete** using a native `<datalist>` of existing tags to reduce fragmentation ("soup" vs "soups").
  - Added a `<datalist>` of the user's existing tags to the recipe form tags input.
  - File: `resources/views/livewire/recipes/manage.blade.php`.
- [x] **Recent scans list / scan history** so failed scans are easy to find again.
  - Added `scans.index` route and component, showing recent scans with status, source, links, and delete.
  - File: `routes/web.php`, `resources/views/livewire/scans/index.blade.php`, `app/Models/User.php`, `app/Policies/RecipeScanPolicy.php`.
- [x] **Recipe preview modal** from the dashboard to quickly glance at ingredients without opening the full page.
  - Added a "Quick view" button on cards that opens a modal with ingredients, steps, and a link to the full recipe.
  - File: `resources/views/livewire/dashboard.blade.php`.

---

## Wave 6 — P2: Public Recipes & Discover

> Goal: let users optionally mark their own recipes as public, browse other users' public recipes in a
> "Discover" tab, and save (bookmark) ones they like without forking them — while keeping mutation
> permissions (edit/delete) strictly owner-only and keeping legal/privacy exposure bounded.
>
> Decisions made up front (see brainstorm in conversation history for full reasoning):
> - **Copyright**: any recipe can be made public, but recipes derived from a URL/image/PDF scan must show
>   a visible source/attribution link when public (no "publish scraped text with no credit").
> - **Audience**: Discover is for logged-in users only — not open to the public internet, not crawlable.
> - **Save vs. copy**: "Save" is a lightweight bookmark-by-reference (new `recipe_saves` pivot), distinct
>   from the existing "Duplicate" action (a full independent fork). Both are offered; they serve different
>   intents. A saved recipe gracefully disappears/shows "no longer available" if the author unpublishes or
>   deletes it — it does not error.
> - `notes` (personal cooking notes) are never exposed on a public recipe, even to someone who saved it.
> - This is separate from the existing private link-sharing feature (`share_token`/`shared_at`,
>   `/shared/{token}`) — that stays as-is for unlisted, no-auth link sharing of a single recipe.

### Phase 1 — Public toggle & policy
- [x] Add `is_public` boolean (default `false`) and `published_at` to `recipes` via migration, with a
  composite `(is_public, created_at)` index for the future Discover query.
  - File: `database/migrations/2026_08_11_131659_add_is_public_to_recipes_table.php`.
- [x] Add `Recipe::makePublic()` / `Recipe::makePrivate()` / `Recipe::wasImported()` helpers (mirroring
  `toggleFavorite()`/`enableSharing()`).
  - File: `app/Models/Recipe.php`.
- [x] Update `RecipePolicy::view` to allow `$recipe->is_public` in addition to ownership. `update`/`delete`
  remain owner-only — no change there.
  - File: `app/Policies/RecipePolicy.php`.
- [x] Add a Public/Private toggle on `recipes.show` (owner-only, near Favorite/Mark cooked), with a
  confirmation on the "make public" direction explaining what it means (visible to every user; personal
  notes stay private; scanned recipes are credited).
- [x] Reuse the existing `duplicate()` action for "Copy to my recipes" on someone else's public recipe —
  now explicitly sets `user_id` to the copier (not the original author) and resets `is_public`,
  `published_at`, `is_favorite`, and `last_cooked_at` on the copy. Button label switches to "Copy to my
  recipes" for non-owners.
  - File: `resources/views/livewire/recipes/show.blade.php`.
- [x] Show a visible source/attribution link on recipes where `wasImported()` is true (URL link if
  available, otherwise "Imported from a photo or PDF").
- [x] Gate owner-only sections (Favorite, Mark cooked, Public toggle, Edit, Delete, Collections, Public
  share link, personal Notes) behind an `$isOwner` check so a non-owner viewing a public recipe only sees
  the recipe content, a "shared by another user" notice, and "Copy to my recipes".
- [x] Tests: policy visibility (owner/public/private-other-user), toggle authorization, duplicate-from-public
  ownership, notes/owner-controls hidden from non-owners, attribution rendering.
  - File: `tests/Feature/RecipeTest.php`.

### Phase 2 — Discover tab
- [x] New `discover` route/Volt component: paginated (12/page, via Volt's `usesPagination()`) browse of
  other users' public recipes (`Recipe::where('is_public', true)->where('user_id', '!=', auth()->id())`
  — a user's own public recipes are deliberately excluded, since they already manage those from "My
  Recipes"; Discover is for finding recipes you don't already have access to), with search
  (title/ingredient) and cuisine filters, plus sort (newest/oldest/title A–Z). Tag filtering was
  intentionally left out of this phase — tags are per-user (`tags.user_id`), so a cross-user tag filter
  needs its own design rather than reusing the dashboard's list.
  - File: `resources/views/livewire/discover/index.blade.php`, `routes/web.php`.
- [x] Composite index on `(is_public, created_at)` for the feed query (added in Phase 1's migration).
- [x] Show minimal author attribution (`$recipe->user->name`) only — no email, no other PII.
- [x] Nav entry alongside Recipes/Collections.
  - File: `resources/views/layouts/app.blade.php`.
- [x] Rate-limit "make public" toggles (20/hour per user) — "make private" is unlimited since it's the safe
  direction. Reuses the inline `RateLimiter` facade pattern already used by login/register.
  - File: `resources/views/livewire/recipes/show.blade.php`.
- [x] Tests: pagination (page 1 vs. page 2 contents), cuisine/search filtering, author name shown, private
  recipes never appear, notes never leak, publish rate limit enforced.
  - Files: `tests/Feature/DiscoverTest.php`, `tests/Feature/RecipeTest.php`.

### Phase 3 — Save (bookmark)
- [x] `recipe_saves` pivot table (`user_id`, `recipe_id`, timestamps, unique on the pair, cascade-deletes on
  either side).
  - File: `database/migrations/2026_08_11_152210_create_recipe_saves_table.php`.
- [x] `User::savedRecipes()` / `User::hasSaved()`.
  - File: `app/Models/User.php`.
- [x] `Save`/`Unsave` actions on Discover cards and `recipes.show` for public recipes (never shown/allowed
  on your own recipe — `abort_if($recipe->user_id === auth()->id(), 403)` as a server-side guard, not just
  a hidden button).
  - Files: `resources/views/livewire/recipes/show.blade.php`, `resources/views/livewire/discover/index.blade.php`.
- [x] Dedicated "Saved" page (`saved.index`), distinct from "My Recipes" and Discover, with a nav entry.
  - File: `resources/views/livewire/saved/index.blade.php`, `routes/web.php`, `resources/views/layouts/app.blade.php`.
- [x] Graceful handling of a since-unpublished/deleted save: the Saved list query filters to
  `is_public = true`, so an unpublished recipe just silently drops out of the list (no error, no dead
  link) — and reappears automatically if the owner re-publishes it later, since the bookmark row itself
  is untouched. An actually-deleted recipe cascade-deletes its `recipe_saves` row via the FK. No explicit
  "no longer available" placeholder was built — the silent-filter behaviour already satisfies "graceful"
  without the extra complexity of tracking why an item disappeared.
- [x] Decision: a saved (not copied) recipe is **not** addable to the user's own Collections in this phase.
  Collections currently assume a straightforward ownership model; wiring in by-reference recipes you don't
  own is a bigger design question (e.g. what happens to a Collection entry when the reference disappears)
  better handled deliberately later if there's real demand, rather than bolted on here.
- [x] Tests: save/unsave from both `recipes.show` and Discover, can't save your own or a private recipe,
  saving twice doesn't duplicate, Saved page scoping (only the current user's saves, author shown),
  unpublish/delete graceful degradation, removing a save from the Saved page itself.
  - File: `tests/Feature/SavedRecipeTest.php`.

### Phase 4 — Optional / later
- [ ] Public author profile page (their public recipes only).
- [ ] Reporting/flagging + lightweight admin moderation for public content.
- [ ] Consider a dedicated public "display name" separate from the account's real `name`, if this grows
  toward a more social feature set (retrofitting later means a migration + UX change for existing users,
  so decide deliberately rather than by default).

---

## Notes

- Items explicitly dropped in `scope.md` (export, ingredient auto-scaling, source retention) are not re-proposed here.
- For any change to the Gemini integration, check the installed `google-gemini-php/laravel` / `gemini-api-php` package docs or Boost `search-docs` first, since the app currently talks to the REST endpoint directly.
