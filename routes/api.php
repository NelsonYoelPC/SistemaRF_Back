<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('usuarios', [UsuarioController::class, 'index']);
Route::post('usuarios', [UsuarioController::class, 'store']);
