<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PerfilController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// HU-02: Perfil de usuario
Route::get('/perfil/{id}', [PerfilController::class, 'show']);
Route::put('/perfil/{id}', [PerfilController::class, 'update']);

Route::post('/perfil/{id}/foto', [PerfilController::class, 'uploadFoto']);