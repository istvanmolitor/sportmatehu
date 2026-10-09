<?php

namespace App\Http\Controllers;

use App\Enums\SyncTargetType;
use App\Http\Requests\StoreSyncTargetRequest;
use App\Models\SyncTarget;
use App\Repositories\Contracts\GithubRepositoryRepositoryInterface;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncTargetController extends Controller
{
    public function __construct(
        private readonly SyncTargetRepositoryInterface $syncTargets,
        private readonly GithubRepositoryRepositoryInterface $repositories,
    ) {}

    /**
     * List the sync targets the authenticated user follows.
     */
    public function index(Request $request): Response
    {
        $targets = $this->syncTargets->allForUser($request->user())
            ->map(fn (SyncTarget $target) => $this->presentTarget($target));

        return Inertia::render('SyncTargets/Index', [
            'syncTargets' => $targets,
        ]);
    }

    /**
     * Add a sync target to the authenticated user's list, creating the
     * globally shared target if it does not exist yet.
     */
    public function store(StoreSyncTargetRequest $request): RedirectResponse
    {
        $this->syncTargets->findOrCreateAndAttachToUser(
            $request->user(),
            $request->string('name')->toString(),
            SyncTargetType::from($request->string('type')->toString()),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sync target added.')]);

        return to_route('sync-targets.index');
    }

    /**
     * Show a single target's synchronized repositories, with search,
     * filtering and sorting.
     */
    public function show(Request $request, SyncTarget $syncTarget): Response
    {
        $this->authorize('view', $syncTarget);

        $filters = $request->only(['search', 'language', 'sort', 'direction']);

        return Inertia::render('SyncTargets/Show', [
            'syncTarget' => $this->presentTarget($syncTarget),
            'repositories' => fn () => $this->repositories->paginateForTarget($syncTarget, $filters),
            'languages' => fn () => $syncTarget->repositories()
                ->whereNotNull('language')
                ->distinct()
                ->orderBy('language')
                ->pluck('language'),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTarget(SyncTarget $target): array
    {
        return [
            'id' => $target->id,
            'name' => $target->name,
            'type' => $target->type->value,
            'status' => $target->status->value,
            'last_synced_at' => $target->last_synced_at?->toIso8601String(),
            'last_sync_error' => $target->last_sync_error,
        ];
    }
}
