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
    total_minutes, difficulty (enum), cuisine, source_type (image|pdf|photo|url),
    source_url (nullable), notes, status, extracted_at, created_at, updated_at`
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

### Phase 6b — Public Read-Only Share Link  *(designed, not yet built)*

Decided this has real value (sharing with people who don't have accounts) but
needs careful scoping so a shared link can **never** expose more than a
read-only view of one recipe's content. Design, to be built as a later phase:

- **Data**: add `recipes.share_token` (nullable, unique, random ~40-char
  URL-safe string) and `recipes.shared_at` (nullable). No token = not shared.
  A "Enable public link" / "Disable public link" toggle on the recipe detail
  page generates/nulls the token (regenerating invalidates the old link).
- **Route**: a dedicated unauthenticated route, e.g.
  `Route::get('shared/{token}', ...)->name('recipes.shared')`, resolved by
  `share_token` lookup only — never reuses `recipes.show`, `RecipePolicy`, or
  route-model-binding by `id` (avoids any chance of ID-based enumeration or
  accidentally falling back to auth-based logic).
- **View**: a new minimal Volt component + a stripped-down guest layout (no
  main nav, no "Recipes / Collections / Profile / Logout", no links back into
  the authenticated app). Renders only title, description, servings/times/
  difficulty/cuisine, ingredients, steps, notes, and tags as plain text/chips
  — no edit/delete/print-with-app-chrome/collection controls, no exposure of
  the owning user's identity beyond perhaps a first name if ever desired.
- **Query scope**: load *only* the recipe + ingredients/steps/tags relations
  needed for display; never eager-load or expose `collections`, `recipe_scans`,
  `user` email, etc.
- **Anti-abuse**: rate-limit the shared route by IP (prevent token
  brute-forcing/scraping) and set `<meta name="robots" content="noindex,nofollow">`
  so shared links aren't crawled/indexed by search engines.
- **Tests**: disabled-by-default (no token = 404), enabling generates a
  working link, disabling/regenerating invalidates the old token, the shared
  page never renders authenticated-only chrome or another user's data, and
  the route is unaffected by `RecipePolicy`.

### Phase 7 — Hardening & Launch prep  *(P2)*
- [ ] Queue reliability (failed_jobs handling, retries, alerting).
- [ ] Move to Postgres/MySQL + real queue driver (Redis/DB) for production.
- [ ] Storage driver for production if retention enabled (S3-ready).
- [ ] Accessibility + mobile QA pass.
- [ ] Deployment (Laravel Cloud) + env/secret management for `GEMINI_API_KEY`.

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
