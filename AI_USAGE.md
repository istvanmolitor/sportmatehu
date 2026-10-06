# AI Usage

## Tools used

- **Claude Code** (Sonnet 5), run as an interactive CLI session against this
  repository. It had shell, file read/write, and browser-automation access.

## How the work was directed

A detailed implementation plan (`PLAN.md`) was written and refined _before_
this session, in Hungarian, covering the data model, repository-pattern
layering, the GitHub integration boundary, the sync service, the queue job,
routes/policy/controllers, the Vue UI, error handling, and the test plan,
plus a 10-step execution checklist.

This session's instruction was simply:

> "Haladjunk végig a PLAN.md alapján a fejlesztéssel. Egyesével haladjunk."
> ("Let's go through the development based on PLAN.md, step by step.")

Each of the 10 checklist steps was implemented one at a time: write the
code, write/run the matching Pest tests, run Pint and Larastan, then move
to the next step only once that step's "done when..." criterion from
`PLAN.md` section 11 was met.

## Areas that were substantially AI-generated

Essentially the whole backend and frontend implementation was written by
Claude Code directly from `PLAN.md`'s specification:

- Migrations, `SyncTarget`/`GithubRepository` models, `SyncTargetType`/
  `SyncStatus` enums, factories.
- The repository-pattern data access layer (`SyncTargetRepositoryInterface`,
  `GithubRepositoryRepositoryInterface`, their Eloquent implementations,
  and the `RepositoryServiceProvider` binding), including the atomic
  `markAsSyncing()` guard and the `UniqueConstraintViolationException`
  retry path for concurrent "add target" requests.
- `GitHubClient`, the `GitHubRepositoryData` DTO, the GitHub exception
  hierarchy, and `RepositorySynchronizer` (transaction boundary, error
  mapping, logging).
- `SyncRepositoriesJob`, the controllers, `StoreSyncTargetRequest`,
  `SyncTargetPolicy`, and `routes/web.php`.
- The Inertia/Vue pages (`SyncTargets/Index.vue`, `SyncTargets/Show.vue`),
  the shared TypeScript types, and the sidebar nav entry.
- All Pest tests (unit/feature) and the named placeholder (`->todo()`)
  tests.

## Examples of suggestions that were changed or rejected

These were caught by tests or manual verification during the session and
corrected before moving on — kept here because they're useful signal about
where a first AI pass tends to go wrong in this stack:

- **Carbon type mismatch.** The first version of `GitHubRepositoryData` and
  `SyncTargetRepositoryInterface::markAsSynced()` typed their Carbon
  parameters as `Illuminate\Support\Carbon`. This app's
  `AppServiceProvider` calls `Date::use(CarbonImmutable::class)`, so
  `now()` actually returns a `CarbonImmutable`, which doesn't satisfy that
  stricter type — a test immediately failed with a `TypeError`. Fixed by
  typing against `Carbon\CarbonInterface` instead.
- **Silent mass-assignment no-op.** `EloquentSyncTargetRepository::
markAsSynced()`/`markAsFailed()` first used `$target->update([...])`.
  Since `SyncTarget`'s `#[Fillable]` attribute only lists `['name',
'type']` (deliberately, so user input can't mass-assign sync state), the
  `status`/`last_sync_error` keys were silently dropped and a test
  expecting the new status failed. Fixed by using `forceFill()->save()`
  for these internal, repository-only state transitions.
- **`Http::fake()` double-registration.** A first draft of the "syncing
  twice doesn't duplicate repositories" test called `Http::fake()` twice
  with different responses for the same URL. Laravel merges stub
  callbacks rather than replacing them, so the _first_ registered
  response kept winning on the second sync, and the test caught stale
  data (100 stars instead of 250). Rewritten using a single
  `Http::sequence()` so each call returns the next queued response.
- **`defineOptions()` can't reference `props`.** `SyncTargets/Show.vue`
  initially built its breadcrumb (which needs the target's name) inside
  `defineOptions({ layout: { breadcrumbs: [...] } })`. Vue's compiler
  rejects this because `defineOptions` is hoisted out of `setup()` and
  can't reference locally declared bindings like `props`. This only
  surfaced when the page was opened in a real browser (not caught by
  `vue-tsc` or the Pest suite). Fixed by switching to Inertia v3's
  `setLayoutProps()`, which runs inside `setup()` and can reference
  `props` normally.
- **Avoided the shadcn-vue `Select` for the add-target form.** The plan
  allowed "radio vagy select" for the user/organization type. A
  headless-UI `Select` component doesn't serialize into a native
  `<form>`'s `FormData` the way the Inertia `<Form>` component expects,
  so native radio inputs were used instead to keep the form wiring
  simple. The free-text filters on the repository list page (language,
  sort, direction) use plain native `<select>` elements for the same
  reason, since they're driven by `router.get()` rather than a native
  form submit.

## How the generated code was validated

- **Automated tests**: the full Pest suite was run after every step
  (migrations/models → repositories → GitHub client → synchronizer → job
  → controllers/policy → UI), finishing at 50 passing tests plus 12
  intentionally skipped/placeholder tests. All external GitHub calls in
  tests are faked via `Http::fake()`/`Http::sequence()`; nothing hits the
  real API during the test suite.
- **Static analysis**: `vendor/bin/pint --test` and `vendor/bin/phpstan
analyse` were run after every backend step and had to pass before
  moving on.
- **Frontend checks**: `vue-tsc --noEmit` (TypeScript) and `npm run check`
  (ESLint/Prettier via vite-plus) were run after the Vue pages were
  written.
- **Manual end-to-end verification in a real browser**: logged in as a
  test user and exercised the full flow against the _real_ GitHub API
  (using the public `octocat` account, which has a small, stable set of
  repositories) — adding a target, starting a sync, watching the status
  go `pending → syncing → success`, browsing the resulting repository
  list, searching by text, filtering by language, sorting by stars, and
  paginating. Also deliberately added a target with a name that can't
  exist on GitHub and confirmed the UI shows the Hungarian human-readable
  error message (not a stack trace), and that `storage/logs/laravel.log`
  contains the debugging context (target id, name, type, exception class,
  message).
