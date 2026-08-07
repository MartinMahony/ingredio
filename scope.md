# Ingredio — Project Scope

> Scan recipes from screenshots, PDFs, phone photos, or a pasted URL, extract the
> structured data (ingredients, measurements, method, timings, etc.), and manage
> them in a personal recipe library.

This document is the living source of truth for the build. Check items off as we go.
Keep the **Decisions** and **Open Questions** sections up to date whenever something
changes.

---

## 1. Decisions (locked)

| Area | Decision |
| --- | --- |
| Backend | Laravel 13, PHP 8.5 |
| Frontend | Livewire + Blade + **Volt** (installed directly — see note) for auth |
| Styling | Tailwind CSS v4 + Vite (already scaffolded) |
| Auth | Multi-user; each user owns their recipes |
| Extraction engine | **Google Gemini** (via Google AI Studio free tier) behind a pluggable driver |
| Processing | Queued / async with a status lifecycle (`pending → processing → ready / failed`) |
| Source file storage | **Transient** — original discarded after successful extraction. `SCAN_KEEP_SOURCE=false` by default |
| Inputs (v1) | Image screenshots (PNG/JPG), PDF, phone camera photos, paste-a-URL |
| Platform | Responsive web app (mobile + desktop browser) |
| Database (dev) | SQLite (default) — Postgres/MySQL ready for later |

---

## 2. Tech Stack & Key Packages

**Already installed:** Laravel 13, Tinker, Pest, Pint, Pail, Boost, Tailwind v4, Vite.

**To add:**

- `livewire/livewire` — reactive UI components.
- Livewire starter kit (auth scaffolding: register / login / password reset / profile).
- `google-gemini-php/laravel` (or `gemini-api-php`) — Gemini client. Wrapped in our
  own `RecipeExtractor` contract so the provider is swappable.
- `spatie/pdf-to-image` **or** send the PDF bytes straight to Gemini (Gemini accepts
  PDFs natively — preferred, avoids the Imagick dependency). Decide in Phase 3.
- `league/html-to-markdown` or a readability library for the paste-a-URL path (fetch
  page → clean text/HTML → send to Gemini).
- Optional later: `spatie/laravel-medialibrary` **only if** we decide to retain sources.

**Dev/quality:** Pest (feature-first), Pint (`vendor/bin/pint --dirty` before commits),
Boost `search-docs` for version-correct Laravel APIs.

---

## 3. High-Level Architecture

```
User (browser)
   │  upload image/pdf  OR  paste URL
   ▼
Livewire component  ──►  creates RecipeScan (status: pending)
   │                         stores source on a temp disk (transient)
   ▼
Dispatch ProcessRecipeScan job  ──►  Queue (already run by `composer run dev`)
   │
   ▼
RecipeExtractor (Gemini driver)
   │  builds prompt + structured JSON schema
   │  sends image / pdf / cleaned URL text to Gemini
   ▼
Parse & validate JSON  ──►  persist Recipe + Ingredients + Steps + Tags
   │
   ├─ success ──►  scan.status = ready, delete source file
   └─ failure ──►  scan.status = failed, record error, (retry policy)
   ▼
Livewire polls scan status  ──►  redirect to editable Recipe review screen
```

**Extraction contract (provider-agnostic):**

```php
interface RecipeExtractor
{
    public function extract(ScanSource $source): ExtractedRecipe; // DTO
}
```

- `GeminiRecipeExtractor implements RecipeExtractor` is the first driver.
- `ScanSource` abstracts image bytes / PDF bytes / URL text.
- `ExtractedRecipe` is a typed DTO validated against our schema before it touches the DB.

---

## 4. Data Model (draft)

> Refine during Phase 2. All recipe-owned tables scope to `user_id`.

- **users** — from starter kit.
- **recipes**
  - `id, user_id, title, description, servings, prep_minutes, cook_minutes,
    total_minutes, difficulty (enum), cuisine, calories (kcal, nullable),
    protein_grams/carbs_grams/fat_grams (per serving, nullable),
    source_type (image|pdf|photo|url), source_url (nullable),
    share_token/shared_at (nullable, public read-only link), notes, status,
    extracted_at, created_at, updated_at`
- **ingredients**
  - `id, recipe_id, group (nullable, e.g. "For the sauce"), position, quantity
    (nullable decimal/string), unit (nullable), name, note (nullable)`
- **recipe_steps**
  - `id, recipe_id, group (nullable), position, instruction, minutes (nullable)`
- **tags** + **recipe_tag** (pivot) — free-form + AI-suggested tags.
- **collections** + **collection_recipe** (pivot) — user-defined groupings.
- **recipe_scans** — the extraction lifecycle record
  - `id, user_id, recipe_id (nullable until ready), source_type, status
    (pending|processing|ready|failed), provider, error (nullable), tokens_used
    (nullable), source_kept (bool), created_at, updated_at`

**Enums:** `RecipeDifficulty`, `ScanStatus`, `ScanSourceType` (TitleCase keys per project style).

---

## 5. Development Phases

Priorities: **P0 = must-have for a usable app**, **P1 = important**, **P2 = nice-to-have**.
Work top-to-bottom; each phase should end green (tests pass, Pint clean).

### Phase 0 — Foundations & Setup  *(P0)*  ✅ Done
- [x] Confirm `.env` DB choice (SQLite dev) and run base migrations.
- [x] Install Livewire + Volt and wire up auth (register / login / forgot / reset / profile).
      **Note:** used Livewire + Volt directly instead of Laravel Breeze — Breeze scaffolds the
      older Tailwind v3 setup and would have overwritten this project's Tailwind v4 config.
- [x] Add `GEMINI_API_KEY` + scan config (`config/scanning.php`, `services.php`, `.env`).
- [x] Base layout, nav, and responsive shell (Tailwind v4). Empty "My Recipes" dashboard.
- [x] `php artisan test` green (14 passing), Pint clean, `.env.example` updated.

### Phase 1 — Recipe CRUD & Library  *(P0)*  ✅ Done
- [x] Migrations + models + factories: `recipes`, `ingredients`, `recipe_steps`.
- [x] Eloquent relationships + policy (user owns recipe) + `RecipeDifficulty` enum.
- [x] Livewire: recipe list (dashboard) with empty state + per-card delete.
- [x] Livewire: recipe detail view (read) — clean, print-friendly layout.
- [x] Livewire: manual create/edit recipe form (repeatable ingredient & step rows).
- [x] Delete recipe (with confirmation) from list and detail page.
- [x] Feature tests for CRUD + authorization (26 tests passing).

### Phase 2 — Extraction Engine (core value)  *(P0)*  ✅ Done
- [x] Define `RecipeExtractor` contract + `ExtractedRecipe` / `ScanSource` DTOs.
- [x] Structured JSON schema/prompt for Gemini (title, servings, times, grouped
      ingredients w/ qty+unit+name, grouped steps, suggested tags, cuisine).
- [x] `GeminiRecipeExtractor` driver (image input first).
- [x] `recipe_scans` migration + model + `ScanStatus` enum.
- [x] `ProcessRecipeScan` queued job: extract → validate → persist → cleanup source.
- [x] Mapping layer: `ExtractedRecipe` DTO → recipe + ingredients + steps.
- [x] Robust error handling + retry policy + failure surfacing.
- [x] Tests with a **faked/mock Gemini response** (no live API in test suite).

### Phase 3 — Upload & Review UX  *(P0)*  ✅ Done
- [x] Livewire upload component: drag-drop + file picker (PNG/JPG/PDF), size/type
      validation, mobile camera capture (`capture` attribute).
- [x] Store source on transient disk; create scan; dispatch job.
- [x] Processing screen with live status (Livewire polling) → "ready" redirect.
- [x] **Review & confirm** screen: pre-filled editable recipe from extraction; user
      corrects before saving. This is the key trust/quality step.
- [x] PDF handling decision: native Gemini PDF vs. rasterize-first. Implement chosen path.
- [x] Delete source file on success (respect `keep_source`).
- [x] Tests: upload validation, job dispatch, status transitions, review save.

### Phase 4 — Paste-a-URL Import  *(P1)*  ✅ Done
- [x] URL input component + validation (SSRF-safe fetch: allowlist scheme, block
      internal IPs, timeout, size cap). `UrlSafetyValidator` blocks non-http(s)
      schemes and re-validates every redirect hop against public IP ranges only.
- [x] Fetch + clean page content (`UrlContentFetcher` + `HtmlTextExtractor`, using
      PHP's built-in DOMDocument — no new dependency needed).
- [x] Feed cleaned text to the same extractor pipeline (`source_type = url`,
      `ScanSource::fromText()`, `recipe_scans.source_url`).
- [x] "Paste a URL" tab added to the existing scan page (`scans.create`).
- [x] Tests with stubbed HTTP responses: SSRF validation, redirect handling, size
      caps, end-to-end job processing, and the Livewire URL flow (18 tests).

### Phase 5 — Organisation: Tags, Collections, Search  *(P1)*  ✅ Done
- [x] `tags` + pivot (user-scoped, unique per user); AI-suggested tags wired
      into `StoreExtractedRecipe` via a shared `SyncRecipeTags` action;
      manual comma-separated tag editing on the recipe form.
- [x] `collections` + pivot; create/delete collections, add/remove a recipe
      to/from a collection from the recipe detail page, dedicated
      `collections.index` / `collections.show` pages, nav link.
- [x] Search + filter library on the dashboard (title, ingredient name,
      cuisine dropdown, tag dropdown), with a distinct "no matches" vs.
      "no recipes yet" empty state.
- [x] Tests for tagging, collections, and search (21 tests).

### Phase 6 — Cost Control  *(P1, re-scoped)*  ✅ Done
- [x] Rate limiting / usage guardrails around the AI calls (cost control) —
      per-user limits (default 5/minute, 20/day, both configurable via
      `SCAN_RATE_LIMIT_PER_MINUTE` / `SCAN_RATE_LIMIT_PER_DAY`) enforced on
      both the file and URL scan actions via `ScanRateLimiter`, using
      Laravel's `RateLimiter` facade. Framed so these could become per-plan
      values later if usage tiers/subscriptions are ever introduced.

**Dropped from the original Phase 6 plan** (decided together, see reasoning below):
- ~~Export recipe (Markdown / JSON / PDF)~~ — the app's value is consolidating
  recipes *in*, not shipping them back out; the existing print stylesheet on
  `recipes.show` already covers "save as PDF" via the browser's print dialog.
  Not worth a dedicated export feature.
- ~~Scale-servings helper~~ — `ingredients.quantity` is a free-text string
  (by design, to preserve AI-extracted values like "a pinch" or "2–3") rather
  than a clean number, so reliable auto-scaling is a real parsing problem, not
  a quick add. A silently-wrong scaled quantity is worse than no feature.
  Revisit only if this becomes a real pain point.
- ~~Source retention + thumbnails~~ — no demand has appeared (per the
  original condition for building it at all); it would also need an
  image-processing dependency and PDF rasterization for thumbnails, and cuts
  against the app's privacy-first delete-by-default stance.

### Phase 6b — Public Read-Only Share Link  *(P2)*  ✅ Done

Built exactly to the design agreed above:

- [x] **Data**: `recipes.share_token` (nullable, unique, random 40-char
      string) + `recipes.shared_at`. Set/cleared only via `Recipe::enableSharing()`
      / `disableSharing()` — intentionally not mass-assignable.
- [x] **Route**: unauthenticated `GET shared/{token}` (`recipes.shared`),
      resolved purely by `share_token` lookup — never touches `RecipePolicy`
      or `id`-based binding. Throttled at `throttle:30,1`.
- [x] **View**: `shared.recipe` Volt component + new minimal `layouts.shared`
      layout (no main nav, no auth links), with
      `<meta name="robots" content="noindex, nofollow">`. Renders only
      title/description/servings/times/difficulty/cuisine/tags/ingredients/
      steps/notes — no edit/delete/print/collections controls.
- [x] **Query scope**: loads only `ingredients`, `steps`, `tags` — never
      `collections`, `recipe_scans`, or user contact info.
- [x] **UI**: "Enable public link" / "Regenerate" / "Disable" controls on
      `recipes.show`, gated by `RecipePolicy::update`.
- [x] Tests (9): default-unshared, 404 for unknown/old tokens, enabling
      works end-to-end, regenerate invalidates the old token, disable
      revokes access, no-auth-required, no app chrome/other-user leakage,
      and the route's rate limit actually triggers a 429.

### Phase 7 — Hardening & Launch prep  *(P2)*

Target: a **Digital Ocean droplet managed via Coolify** (not Laravel Cloud),
sharing the droplet with other apps. Decisions made together:

| Area | Decision |
| --- | --- |
| Production DB | **MySQL/MariaDB** (own DB, same droplet) — chosen over Postgres specifically so the existing phpMyAdmin instance can still be used (phpMyAdmin cannot connect to Postgres at all). |
| Production queue | **Redis**, via `predis` (pure PHP client — no `redis` PHP extension needed, more portable across containers). |
| Deployment | **Coolify**, using its Nixpacks auto-detect build, with a project-supplied `nginx.template.conf` (see below) to work around a bug in Nixpacks' own default PHP nginx template. |
| Error tracking | **Sentry** (existing account) for unhandled exceptions/queue failures, beyond the in-app "scan failed" UI status. |
| Source retention/S3 storage | Dropped — no demand, and Phase 6 already ruled out retention. |

- [x] **Queue reliability**: `failed_jobs` table already provisioned by
      Laravel's default migrations; `ProcessRecipeScan` already has
      `tries=3`/`backoff=10` and marks the scan failed for the user to see.
      Sentry now also captures queue job failures/unhandled exceptions
      server-side (see below).
- [x] **Redis queue driver**: added `predis/predis`; production `.env` should
      set `QUEUE_CONNECTION=redis` + `REDIS_*` (client is `predis`, not
      `phpredis`). Dev/test keep `QUEUE_CONNECTION=database`/`sync` — no
      Redis needed locally.
- [x] **MySQL/MariaDB for production**: no code changes needed (Laravel's
      migrations are DB-agnostic); production `.env` sets
      `DB_CONNECTION=mysql` + credentials for the app's own database.
- [x] **Sentry**: `sentry/sentry-laravel` installed, `config/sentry.php`
      published (env-driven, safe no-op with an empty DSN), wired into
      `bootstrap/app.php`'s `withExceptions()`. Production `.env` needs
      `SENTRY_LARAVEL_DSN` set.
- [x] **Reverse proxy trust**: `bootstrap/app.php` now calls
      `$middleware->trustProxies(at: '*')` so HTTPS detection, secure
      cookies, and generated URLs are correct behind Coolify's Traefik proxy.
- [x] **Accessibility + mobile QA pass**: added `aria-label`s to icon-only
      buttons and unlabeled inputs/selects (dashboard search/filters, recipe
      form repeatable rows, share-link controls, collection chips),
      `aria-hidden="true"` on purely decorative SVG icons, `role="status"
      aria-live="polite"` on the scan-processing screen and `role="alert"` on
      the scan-failed screen so screen readers announce state changes, and
      `aria-pressed` on the upload/URL mode toggle. Layout already used
      responsive `sm:`/`lg:` breakpoints throughout; no structural changes
      needed for mobile.
- [ ] **Manual Coolify setup** (droplet access required, not done from here):
      1. Create a Coolify application resource (Nixpacks/PHP) pointed at this
         repo, plus a MySQL/MariaDB database service and a Redis service.
      2. Set env vars in Coolify: `APP_KEY` (generate fresh), `DB_*`,
         `REDIS_*`, `QUEUE_CONNECTION=redis`, `GEMINI_API_KEY`,
         `SENTRY_LARAVEL_DSN`, `SCAN_*`, mail settings, `APP_URL`.
      3. Post-deployment command: `php artisan migrate --force` (add
         `config:cache`/`route:cache`/`view:cache` once env is stable).
      4. A **second** Coolify resource on the same repo/image, with the start
         command overridden to `php artisan queue:work --tries=3 --backoff=10 --timeout=120 --sleep=1`
         — Laravel's queue worker needs its own long-running process,
         separate from the web resource. The `--timeout=120` must be larger than
         `ProcessRecipeScan`'s 90 s job timeout. Set `DB_QUEUE_RETRY_AFTER=180`
         (or `REDIS_QUEUE_RETRY_AFTER=180` if using Redis) to be larger than the
         worker timeout. Use `--sleep=1` for the DB driver to reduce pickup latency.
      5. Point Coolify's health check at `/up` (Laravel's built-in health
         route, already present).
      6. Attach a domain/subdomain — Coolify/Traefik handles TLS automatically.

### Deployment fix — nginx.template.conf  ✅ Done
- [x] First deploy attempt hit `nginx: [emerg] duplicate location "/" in
      /nginx.conf` on every boot (crash-restart loop → Traefik "no available
      server"). Root cause is a genuine bug in **Nixpacks' own default PHP
      nginx template**: it unconditionally renders a `location /` block for
      `IS_LARAVEL` (auto-set because `artisan` exists) *and* a second
      `location /` block for `NIXPACKS_PHP_FALLBACK_PATH` whenever both are
      set — producing invalid nginx config. It also defaults `root` to
      `/app` rather than `/app/public` unless `NIXPACKS_PHP_ROOT_DIR` is set.
- [x] Fixed by adding a project-root `nginx.template.conf` (Nixpacks
      auto-detects and uses it in place of its bundled template) — same file
      as upstream, minus the fallback-path block, with `root /app/public`
      hardcoded. No Coolify env var juggling required; the fix lives in the
      repo and is immune to whatever auto-populates `NIXPACKS_PHP_FALLBACK_PATH`
      on the Coolify side.
- [x] `recipes.calories/protein_grams/carbs_grams/fat_grams` (all nullable,
      per serving). Extraction prompt/schema updated so Gemini transcribes
      these **only when explicitly stated in the source** — never
      calculated/estimated, to avoid presenting fabricated nutrition data as
      fact.
- [x] Manual "Nutrition (per serving)" fields on the recipe create/edit form.
- [x] Displayed on both `recipes.show` and the public `shared.recipe` page
      when present, with a note that values are per serving as stated in
      the source; hidden entirely when absent.
- [x] Tests (8): extraction with/without nutrition present, manual
      create/edit, validation, and display on both recipe pages.

### Deployment fix — source file race condition across containers  ✅ Done
- [x] Once the web app and queue worker ran as separate Coolify resources
      sharing scan uploads via a mounted directory, file-based scans
      (image/PDF) became **intermittently** unreliable — the same file that
      extracted perfectly when run directly would occasionally come back
      with near-empty/garbled data in production. Root cause: the worker
      container could read the file before the web container's write had
      fully synced across the shared mount — a classic race condition,
      consistent with the failures being non-deterministic rather than
      always-broken.
- [x] Fixed by recording `recipe_scans.source_size` (the upload's byte size)
      at upload time, and having `ProcessRecipeScan` verify the file it
      reads back matches that size before sending it to Gemini. A mismatch
      throws, which the job's existing retry/backoff (`tries=3`,
      `backoff=10`) picks up a few seconds later — by which point the write
      has synced — rather than silently processing a truncated file.
      Backward-compatible with any scan rows that predate this column
      (`source_size` nullable; check is skipped when null).
- [x] Tests (3): mismatch throws and is retryable, matching size processes
      normally, `source_size = null` (pre-existing rows) skips the check.

---

## 6. Cross-Cutting Concerns

- **Security:** enforce ownership via policies; validate/limit uploads (mime, size);
  SSRF protection on URL fetch; never log API keys; sanitise AI output before render.
- **Cost control:** free-tier limits on Gemini — add per-user rate limits and a
  monthly cap; log `tokens_used` per scan.
- **Privacy:** transient source files deleted after processing by default.
- **Testing:** feature-first with Pest; mock the AI provider — no live calls in CI.
- **Style:** follow project PHP conventions (`if(...)`, braces on new line, omit braces
  for single statements, `!` negation spacing). Run `vendor/bin/pint --dirty` before commit.

---

## 7. Open Questions / To Decide Later

- [ ] Which Gemini model (e.g. `gemini-2.0-flash` for cost vs. a pro model for accuracy)?
- [ ] PDF strategy: native Gemini PDF ingestion vs. rasterise pages first?
- [ ] Handling multi-recipe documents / very long PDFs (split? first recipe only?).
- [ ] Measurement normalisation (metric ↔ imperial) — auto-convert or store as-scanned?
- [ ] Ingredient parsing granularity (separate qty/unit/name reliably from free text)?
- [ ] Do we ever need offline/manual OCR fallback if AI quota is exhausted?
- [ ] Public sharing / collaboration scope (future).

---

## 8. Definition of "MVP Ready"

A logged-in user can:
1. Upload a screenshot/PDF **or** snap a phone photo of a recipe.
2. Watch it process asynchronously.
3. Review and correct the extracted recipe.
4. Save it to their library, view it cleanly, edit it, and delete it.

Phases 0–3 deliver this. Everything after is enhancement.
