<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Models\User;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;

test('findOrCreateAndAttachToUser creates a single global target shared by two users', function () {
    $repository = app(SyncTargetRepositoryInterface::class);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $targetForA = $repository->findOrCreateAndAttachToUser($userA, 'laravel', SyncTargetType::Organization);
    $targetForB = $repository->findOrCreateAndAttachToUser($userB, 'laravel', SyncTargetType::Organization);

    expect(SyncTarget::query()->count())->toBe(1);
    expect($targetForA->id)->toBe($targetForB->id);
    expect($repository->allForUser($userA)->pluck('id'))->toContain($targetForA->id);
    expect($repository->allForUser($userB)->pluck('id'))->toContain($targetForB->id);
});

test('findOrCreateAndAttachToUser does not duplicate the pivot row when called twice for the same user', function () {
    $repository = app(SyncTargetRepositoryInterface::class);
    $user = User::factory()->create();

    $repository->findOrCreateAndAttachToUser($user, 'laravel', SyncTargetType::Organization);
    $repository->findOrCreateAndAttachToUser($user, 'laravel', SyncTargetType::Organization);

    expect($user->syncTargets()->count())->toBe(1);
});

test('markAsSyncing atomically transitions the target exactly once', function () {
    $repository = app(SyncTargetRepositoryInterface::class);
    $target = SyncTarget::factory()->create(['status' => SyncStatus::Pending]);

    $first = $repository->markAsSyncing($target);
    $second = $repository->markAsSyncing($target);

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
    expect($target->refresh()->status)->toBe(SyncStatus::Syncing);
});

test('markAsSynced records the success state and clears the previous error', function () {
    $repository = app(SyncTargetRepositoryInterface::class);
    $target = SyncTarget::factory()->failed()->create();
    $now = now();

    $repository->markAsSynced($target, $now);

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Success);
    expect($target->last_synced_at->timestamp)->toBe($now->timestamp);
    expect($target->last_sync_error)->toBeNull();
});

test('markAsFailed records the status and human-readable error', function () {
    $repository = app(SyncTargetRepositoryInterface::class);
    $target = SyncTarget::factory()->create();

    $repository->markAsFailed($target, 'GitHub nem található felhasználó/organizáció ezzel a névvel.');

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Failed);
    expect($target->last_sync_error)->toBe('GitHub nem található felhasználó/organizáció ezzel a névvel.');
});
