# Notes

## Approximate time spent

_TODO: fill in — this file was produced in a single AI-assisted Claude Code
session that implemented `PLAN.md` end to end; the actual human time spent
directing/reviewing it should be recorded here before hand-in._

## Completed parts (baseline, README sections 1-6)

- **Synchronization target**: add a GitHub user/org with validation
  (`name` required, `type` in `user|organization`), globally deduplicated
  via a `(name, type)` unique constraint, visible per-user through a
  `sync_target_user` pivot table.
- **GitHub integration boundary**: `GitHubClient` with a dedicated DTO
  (`GitHubRepositoryData`), full pagination via the `Link` response
  header, and dedicated exceptions for 404 / rate-limit / generic API
  failures.
- **Local repository storage**: `repositories` table with a
  `(sync_target_id, github_id)` unique constraint; `upsertMany()` creates
  new rows and updates existing ones without duplicating.
- **Queued synchronization**: `SyncRepositoriesJob` on the database queue;
  duplicate sync requests for the same target are rejected atomically via
  `markAsSyncing()`'s conditional update (not a read-then-write check).
- **Inertia/Vue UI**: list + add target, trigger sync, view status/last
  error, browse a target's repositories with text search, language
  filter, and sortable columns (stars / open issues / GitHub last
  updated), all backend-paginated.
- **Tests**: 50 passing Pest tests covering target creation/validation,
  the shared-target dedup, atomic `markAsSyncing()`, the GitHub client
  (endpoint selection, pagination, 404/403/5xx), the synchronizer
  (success, duplicate-safe upsert, failure path), the job, and the HTTP
  layer (authorization, duplicate-sync rejection). Plus 9 named
  placeholder (`->todo()`) tests for explicitly out-of-scope scenarios.
- **`AI_USAGE.md`**: see that file for tooling, prompts, and validation.

## Incomplete parts / deliberately out of scope

- **Scheduled synchronization, REST API, README full-text search,
  reconciliation** ("if you have time" list) — not implemented; see
  "what's next" below.
- **Simple multitenancy**: handled differently than the bonus item
  describes. Instead of fully isolated per-user data, `PLAN.md` documents
  a deliberate choice of a **shared global catalog** (`sync_targets` /
  `repositories` are not duplicated per user) with **per-user visibility**
  via the `sync_target_user` pivot and a `SyncTargetPolicy`. This reads as
  a closer fit for the README's dedup requirement than per-user
  duplication would be, at the cost of not being literal "multitenancy".
- **Job retry/backoff, `WithoutOverlapping` middleware, `$timeout`
  tuning**: intentionally left at the comment/note level in
  `SyncRepositoriesJob`'s docblock, per `PLAN.md` section 5 — the
  controller-level atomic `markAsSyncing()` guard is the one piece that
  _is_ implemented and tested.
- **Automatic retry on GitHub rate limiting**: `GitHubRateLimitException`
  captures the `Retry-After`/`X-RateLimit-Reset` value, but nothing
  currently acts on it automatically (no backoff/retry loop).
- **Cache invalidation**: not applicable — the baseline has no caching
  layer in front of the target/repository listings (`CACHE_STORE=database`
  is only used for Laravel's own session/cache mechanisms). If listings
  were cached later, `upsertMany()` would be the natural place to add
  `Cache::forget()`/tag-based invalidation.
- **True concurrency testing**: the "two users add the same target"
  dedup is tested sequentially (two requests, one after another), which
  exercises the `firstOrCreate` happy path but not the
  `UniqueConstraintViolationException` retry branch itself, since Pest
  runs against a single SQLite connection with no real parallelism.

## What would be implemented next

In rough priority order:

1. **Job retry/backoff + `WithoutOverlapping`** — highest value for the
   effort: a few lines on `SyncRepositoriesJob` ($tries, backoff(),
   failed() hook, uniqueId()) turn the current notes into real
   defense-in-depth alongside the controller-level guard.
2. **Scheduled synchronization** — an Artisan command plus a scheduler
   entry to re-sync existing targets periodically (e.g. every few hours;
   repository metadata like star counts doesn't change fast enough to
   justify anything near-real-time, and it keeps well within GitHub's
   anonymous rate limit for a handful of targets).
3. **Reconciliation** — mark repositories no longer returned by GitHub
   (e.g. an `is_missing`/`disappeared_at` column set during `upsertMany()`
   for rows not present in the latest sync) rather than leaving them
   silently stale forever.
4. **REST API** for the hypothetical mobile client, with consistent JSON
   responses (API Resources) and status codes, reusing the existing
   repository layer.
5. **README full-text search** — would need to fetch and store README
   content separately (it isn't part of the repos list endpoint), with a
   simple `LIKE` baseline and a note about FTS/Scout for production scale.

## Important compromises / shortcuts

- **Minimal styling**: native `<select>`/radio inputs instead of the
  project's shadcn-vue `Select` component (simpler to wire up to Inertia's
  `<Form>` component and `router.get()`), no dedicated table/pagination UI
  components — plain Tailwind-styled HTML tables, per the README's "do not
  spend much time on styling" guidance.
- **Whole-org fetch per sync**: `GitHubClient::getRepositoriesFor()`
  collects all pages into memory before `RepositorySynchronizer` persists
  them. Fine for typical users/orgs; an org with many thousands of
  repositories would benefit from incremental/streamed persistence
  instead.
- **UI/error copy in Hungarian**: user-facing strings (error messages,
  button labels, page copy) are in Hungarian, matching the language
  `PLAN.md` was written and reviewed in; code identifiers, comments, and
  commit-adjacent docs are in English.
