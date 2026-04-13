<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\HabilidadController;

// Rutas públicas
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Perfil
    Route::get('/perfil', [PerfilController::class, 'show']);
    Route::put('/perfil', [PerfilController::class, 'update']);
    Route::post('/perfil/password', [PerfilController::class, 'changePassword']);
    Route::post('/perfil/avatar', [PerfilController::class, 'uploadAvatar']);
    Route::delete('/perfil/deactivate', [PerfilController::class, 'deactivate']);

    // Habilidades
    Route::get('/habilidades/catalogo', [App\Http\Controllers\Api\HabilidadController::class, 'catalogo']);
    Route::get('/habilidades/mis', [App\Http\Controllers\Api\HabilidadController::class, 'misHabilidades']);
    Route::post('/habilidades/agregar', [App\Http\Controllers\Api\HabilidadController::class, 'agregar']);
    Route::put('/habilidades/actualizar-nivel', [App\Http\Controllers\Api\HabilidadController::class, 'actualizarNivel']);
    Route::delete('/habilidades/eliminar', [App\Http\Controllers\Api\HabilidadController::class, 'eliminar']);
});