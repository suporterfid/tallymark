<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->post('/login', [SessionController::class, 'store']);
Route::middleware('auth')->post('/logout', [SessionController::class, 'destroy']);
Route::get('/app', static fn () => redirect('/build/dashboard/'))->middleware('auth');

Route::get('/', function () {
    return view('welcome');
});
