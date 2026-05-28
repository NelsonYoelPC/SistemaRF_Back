<?php

use App\Http\Controllers\PersonaInteresController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AlertaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('usuarios', [UsuarioController::class, 'index']);
Route::post('usuarios', [UsuarioController::class, 'store']);
Route::get('usuarios/{id}', [UsuarioController::class, 'show']);
Route::put('usuarios/{id}', [UsuarioController::class, 'update']);
Route::patch('/usuarios/{id}/estado', [UsuarioController::class, 'updateEstado']);

// Personas de Interés (Vigilancia)
Route::get('personas-interes', [PersonaInteresController::class, 'index']);
Route::post('personas-interes', [PersonaInteresController::class, 'store']);
Route::put('personas-interes/{id}', [PersonaInteresController::class, 'update']);
Route::delete('personas-interes/{id}', [PersonaInteresController::class, 'destroy']);

Route::get('roles', [RolController::class, 'index']);
Route::get('roles/{id}', [RolController::class, 'show']);
Route::post('roles', [RolController::class, 'store']);
Route::put('roles/{id}', [RolController::class, 'update']);
Route::patch('/roles/{id}/estado', [RolController::class, 'updateEstado']);

// Alertas
Route::post('/alertas', [AlertaController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
