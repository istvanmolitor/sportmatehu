<?php

/**
 * Named placeholder tests for scenarios intentionally left as notes rather
 * than full implementations in this baseline (see PLAN.md sections 5, 8
 * and 9, and the README's "if you have time" list). Each one states what
 * it would verify once implemented.
 */
test('GitHub rate limit responses trigger an automatic retry with backoff instead of failing immediately')->todo();

test('the job retries a transient GitHub failure up to $tries times before being marked as failed')->todo();

test('a job that exhausts its retries is recorded in the failed_jobs table and the target is marked failed via the failed() hook')->todo();

test('a WithoutOverlapping queue middleware prevents two queued jobs for the same target from running concurrently, as defense-in-depth alongside the controller-level markAsSyncing() guard')->todo();

test('a scheduled command re-syncs all existing targets on a fixed interval')->todo();

test('repositories no longer returned by GitHub are reconciled (e.g. marked stale or removed) instead of being left stale forever')->todo();

test('the repository listing cache, if introduced, is invalidated after upsertMany() persists new data')->todo();

test('adding a sync target with an invalid GitHub name (special characters, excessive length) is rejected with a clear validation message')->todo();

test('two genuinely concurrent requests adding the same new sync target both succeed without a duplicate sync_targets row, exercising the UniqueConstraintViolationException retry path')->todo();
