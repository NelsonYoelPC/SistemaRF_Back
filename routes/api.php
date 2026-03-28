<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RolController;

Route::get('usuarios', [UsuarioController::class, 'index']);
Route::post('usuarios', [UsuarioController::class, 'store']);
Route::get('usuarios/{id}', [UsuarioController::class, 'show']);
Route::put('usuarios/{id}', [UsuarioController::class, 'update']);
Route::patch('/usuarios/{id}/estado', [UsuarioController::class, 'updateEstado']);

Route::get('roles', [RolController::class, 'index']);
Route::get('roles/{id}', [RolController::class, 'show']);
Route::post('roles', [RolController::class, 'store']);
Route::put('roles/{id}', [RolController::class, 'update']);
Route::patch('/roles/{id}/estado', [RolController::class, 'updateEstado']);
