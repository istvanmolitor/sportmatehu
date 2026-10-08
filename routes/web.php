<?php

use App\Http\Controllers\SyncTargetController;
use App\Http\Controllers\SyncTargetSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('sync-targets.index')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('sync-targets', [SyncTargetController::class, 'index'])->name('sync-targets.index');
    Route::post('sync-targets', [SyncTargetController::class, 'store'])->name('sync-targets.store');
    Route::get('sync-targets/{syncTarget}', [SyncTargetController::class, 'show'])->name('sync-targets.show');
    Route::post('sync-targets/{syncTarget}/sync', [SyncTargetSyncController::class, 'store'])->name('sync-targets.sync');
});

require __DIR__.'/settings.php';
