<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::middleware('guest')->group(function () {
    Route::view('/register', 'account', ['mode' => 'register'])->name('register');
    Route::view('/login', 'account', ['mode' => 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::view('/profile', 'account', ['mode' => 'profile'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
