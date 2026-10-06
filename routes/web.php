<?php

use App\Http\Controllers\SyncTargetController;
use App\Http\Controllers\SyncTargetSyncController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('sync-targets', [SyncTargetController::class, 'index'])->name('sync-targets.index');
    Route::post('sync-targets', [SyncTargetController::class, 'store'])->name('sync-targets.store');
    Route::get('sync-targets/{syncTarget}', [SyncTargetController::class, 'show'])->name('sync-targets.show');
    Route::post('sync-targets/{syncTarget}/sync', [SyncTargetSyncController::class, 'store'])->name('sync-targets.sync');
});

require __DIR__.'/settings.php';
