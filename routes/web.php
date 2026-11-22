<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StudioController::class, 'index'])->name('home');
Route::get('/studios/{studio}', [StudioController::class, 'show'])->name('studios.show');
Route::middleware('guest')->group(function () {
    Route::view('/register', 'account', ['mode' => 'register'])->name('register');
    Route::view('/login', 'account', ['mode' => 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::view('/profile', 'account', ['mode' => 'profile'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/studios/{studio}/join', [StudioController::class, 'join'])->name('studios.join');
    Route::post('/studios/{studio}/memberships/{membership}', [StudioController::class, 'decide'])->name('memberships.decide');
    Route::post('/studios/{studio}/courts', [StudioController::class, 'courts'])->name('studios.courts');
    Route::post('/studios/{studio}/credits', [CreditController::class, 'store'])->name('credits.store');
    Route::post('/studios/{studio}/credits/{credit}', [CreditController::class, 'decide'])->name('credits.decide');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
