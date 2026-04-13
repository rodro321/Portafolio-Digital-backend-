<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// GitHub OAuth - necesita sesiones (web middleware)
Route::get('/api/auth/github/redirect', [AuthController::class, 'redirectToGitHub']);
Route::get('/api/auth/github/callback', [AuthController::class, 'handleGitHubCallback']);