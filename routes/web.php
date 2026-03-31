<?php

use App\Http\Controllers\Admin\DatabaseBackupRestoreController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PosViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Tarweaa POS — Web (Blade) Routes
|
*/

Route::get('/', [PortalController::class, 'entry'])->name('portal.entry');
Route::get('/login', [PortalController::class, 'entry'])->name('login');

Route::middleware('guest')->group(function () {
    Route::post('/portal/login', [PortalController::class, 'login'])->name('portal.login');
    Route::post('/portal/pin-login', [PortalController::class, 'pinLogin'])->name('portal.pin-login');
});

Route::middleware('auth')->group(function () {
    Route::get('/launcher', [PortalController::class, 'launcher'])->name('portal.launcher');
    Route::post('/portal/logout', [PortalController::class, 'logout'])->name('portal.logout');
    Route::post('/portal/password', [PortalController::class, 'updatePassword'])->name('portal.password');
});

Route::prefix('pos')->group(function () {
    Route::get('/login', [PortalController::class, 'entry'])
        ->defaults('default_redirect', '/pos/drawer')
        ->name('pos.login');
    Route::middleware(['surface:pos'])->group(function () {
        Route::get('/drawer',       [PosViewController::class, 'drawerOpen'])->name('pos.drawer');
        Route::get('/',             [PosViewController::class, 'pos'])->name('pos.main');
        Route::get('/close-drawer', [PosViewController::class, 'drawerClose'])->name('pos.close-drawer');
    });
});

Route::get('/kitchen', [\App\Http\Controllers\KitchenController::class, 'index'])
    ->middleware(['surface:kitchen'])
    ->name('kitchen.index');

Route::get('/counter', [\App\Http\Controllers\CounterScreenController::class, 'index'])
    ->middleware(['surface:counter'])
    ->defaults('lane', 'all')
    ->name('counter.all');

Route::get('/counter/{lane}', [\App\Http\Controllers\CounterScreenController::class, 'index'])
    ->middleware(['surface:counter'])
    ->whereIn('lane', ['odd', 'even'])
    ->name('counter.index');

Route::get('/counter-screen/{lane}', fn (string $lane) => redirect("/counter/{$lane}", 301))
    ->middleware(['surface:counter'])
    ->whereIn('lane', ['odd', 'even']);

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::post('/database-backups/restore', [DatabaseBackupRestoreController::class, 'store'])
        ->name('admin.database-backups.restore');
});
