<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\HabilidadController;
use App\Http\Controllers\ProyectoController;

/*
|--------------------------------------------------------------------------
| API Routes — Sistema de Portafolios Digitales
|--------------------------------------------------------------------------
|
|  Prefijo global: /api  (configurado en RouteServiceProvider)
|
|  ── Pública ───────────────────────────────────────────────────────────
|  POST   /api/auth/registro
|  POST   /api/auth/login
|  GET    /api/auth/github              → redirige a GitHub OAuth
|  GET    /api/auth/github/callback     → callback de GitHub OAuth
|  GET    /api/habilidades/catalogo
|
|  ── Protegida (Sanctum) ────────────────────────────────────────────────
|  GET    /api/auth/me
|  POST   /api/auth/logout
|
|  GET    /api/usuario/perfil
|  PUT    /api/usuario/perfil
|  POST   /api/usuario/foto
|  PUT    /api/usuario/password
|  DELETE /api/usuario
|
|  GET    /api/habilidades
|  POST   /api/habilidades
|  POST   /api/habilidades/personalizada
|  PUT    /api/habilidades/{id}/nivel
|  DELETE /api/habilidades/{id}
|  PUT    /api/habilidades/sincronizar
|
|  GET    /api/proyectos
|  POST   /api/proyectos
|  GET    /api/proyectos/{id}
|  PUT    /api/proyectos/{id}
|  DELETE /api/proyectos/{id}
|  POST   /api/proyectos/{id}/imagenes
|  DELETE /api/proyectos/{id}/imagenes/{idImagen}
|  PUT    /api/proyectos/{id}/habilidades
|
*/

// ── Rutas públicas ───────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('registro', [AuthController::class, 'registro']);
    Route::post('login',    [AuthController::class, 'login']);

    // GitHub OAuth
    Route::get('github',          [AuthController::class, 'githubRedirect']);
    Route::get('github/callback', [AuthController::class, 'githubCallback']);
});

// Catálogo de habilidades (no requiere auth)
Route::get('habilidades/catalogo', [HabilidadController::class, 'catalogo']);

// ── Rutas protegidas ─────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Usuario
    Route::prefix('usuario')->group(function () {
        Route::get('perfil',     [UsuarioController::class, 'perfil']);
        Route::put('perfil',     [UsuarioController::class, 'actualizarPerfil']);
        Route::post('foto',      [UsuarioController::class, 'actualizarFoto']);
        Route::put('password',   [UsuarioController::class, 'cambiarPassword']);
        Route::delete('/',       [UsuarioController::class, 'desactivar']);
    });

    // Habilidades
    Route::prefix('habilidades')->group(function () {
        Route::get('/',                         [HabilidadController::class, 'listar']);
        Route::post('/',                        [HabilidadController::class, 'agregar']);
        Route::post('personalizada',            [HabilidadController::class, 'agregarPersonalizada']);
        Route::put('sincronizar',               [HabilidadController::class, 'sincronizar']);
        Route::put('{idHabilidad}/nivel',       [HabilidadController::class, 'editarNivel']);
        Route::delete('{idHabilidad}',          [HabilidadController::class, 'eliminar']);
    });

    // Proyectos
    Route::prefix('proyectos')->group(function () {
        Route::get('/',                             [ProyectoController::class, 'listar']);
        Route::post('/',                            [ProyectoController::class, 'crear']);
        Route::get('{idProyecto}',                  [ProyectoController::class, 'obtener']);
        Route::put('{idProyecto}',                  [ProyectoController::class, 'actualizar']);
        Route::delete('{idProyecto}',               [ProyectoController::class, 'eliminar']);
        Route::post('{idProyecto}/imagenes',        [ProyectoController::class, 'agregarImagen']);
        Route::delete('{idProyecto}/imagenes/{idImagen}', [ProyectoController::class, 'eliminarImagen']);
        Route::put('{idProyecto}/habilidades',      [ProyectoController::class, 'sincronizarHabilidades']);
    });
});