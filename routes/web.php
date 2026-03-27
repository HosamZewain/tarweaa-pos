<?php

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

Route::redirect('/', '/pos/login');
Route::redirect('/login', '/pos/login')->name('login');

Route::prefix('pos')->group(function () {
    Route::get('/login',        [PosViewController::class, 'login'])->name('pos.login');
    Route::get('/drawer',       [PosViewController::class, 'drawerOpen'])->name('pos.drawer');
    Route::get('/',             [PosViewController::class, 'pos'])->name('pos.main');
    Route::get('/close-drawer', [PosViewController::class, 'drawerClose'])->name('pos.close-drawer');
});

Route::get('/kitchen', [\App\Http\Controllers\KitchenController::class, 'index'])
    ->name('kitchen.index');
