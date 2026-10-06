<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Jobs\SyncRepositoriesJob;
use App\Models\SyncTarget;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('guests are redirected to the login page', function () {
    $this->get(route('sync-targets.index'))->assertRedirect(route('login'));
});

test('a user can add a sync target with valid data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sync-targets.store'), [
        'name' => 'laravel',
        'type' => 'organization',
    ]);

    $response->assertRedirect(route('sync-targets.index'));
    expect(SyncTarget::query()->where('name', 'laravel')->exists())->toBeTrue();
    expect($user->syncTargets()->where('name', 'laravel')->exists())->toBeTrue();
});

test('adding a sync target requires a name and a valid type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sync-targets.store'), [
        'name' => '',
        'type' => 'not-a-real-type',
    ]);

    $response->assertInvalid(['name', 'type']);
});

test('a user cannot add the same sync target to their own list twice', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('sync-targets.store'), ['name' => 'laravel', 'type' => 'organization']);

    $response = $this->actingAs($user)->post(route('sync-targets.store'), ['name' => 'laravel', 'type' => 'organization']);

    $response->assertInvalid(['name']);
    expect($user->syncTargets()->count())->toBe(1);
});

test('two different users adding the same target share a single global sync target', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)->post(route('sync-targets.store'), ['name' => 'laravel', 'type' => 'organization']);
    $this->actingAs($userB)->post(route('sync-targets.store'), ['name' => 'laravel', 'type' => 'organization']);

    expect(SyncTarget::query()->where('name', 'laravel')->where('type', SyncTargetType::Organization)->count())->toBe(1);
});

test('a user cannot view a sync target they have not added', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $target = SyncTarget::factory()->create();
    $owner->syncTargets()->attach($target);

    $this->actingAs($stranger)->get(route('sync-targets.show', $target))->assertForbidden();
});

test('a user can view a sync target they have added', function () {
    $user = User::factory()->create();
    $target = SyncTarget::factory()->create();
    $user->syncTargets()->attach($target);

    $this->actingAs($user)->get(route('sync-targets.show', $target))->assertOk();
});

test('starting a sync dispatches the job and rejects a second concurrent request', function () {
    Queue::fake();

    $user = User::factory()->create();
    $target = SyncTarget::factory()->create(['status' => SyncStatus::Pending]);
    $user->syncTargets()->attach($target);

    $first = $this->actingAs($user)->post(route('sync-targets.sync', $target));
    $second = $this->actingAs($user)->post(route('sync-targets.sync', $target));

    $first->assertRedirect();
    $second->assertRedirect();
    expect($target->refresh()->status)->toBe(SyncStatus::Syncing);
    Queue::assertPushed(SyncRepositoriesJob::class, 1);
});

test('a user cannot start a sync for a target they have not added', function () {
    $stranger = User::factory()->create();
    $target = SyncTarget::factory()->create();

    $this->actingAs($stranger)->post(route('sync-targets.sync', $target))->assertForbidden();
});
