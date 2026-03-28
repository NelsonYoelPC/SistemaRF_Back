<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['role', 'usuario'])
            ->where('name', $request->name)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'name' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene un rol asignado.',
            ], 403);
        }

        if ((int) $user->role->estado !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'El rol asignado al usuario está inactivo.',
            ], 403);
        }

        if ($user->usuario && (int) $user->usuario->estado !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario asociado está inactivo.',
            ], 403);
        }

        $fullName = $user->usuario
            ? trim(implode(' ', array_filter([
                $user->usuario->nombres,
                $user->usuario->apellido_paterno,
                $user->usuario->apellido_materno,
            ])))
            : $user->name;

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión correcto.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'usuario_id' => $user->usuario_id,
                    'username' => $user->name,
                    'full_name' => $fullName,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role->codigo,
                    'role_description' => $user->role->descripcion,
                ]
            ]
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'usuario']);

        $fullName = $user->usuario
            ? trim(implode(' ', array_filter([
                $user->usuario->nombres,
                $user->usuario->apellido_paterno,
                $user->usuario->apellido_materno,
            ])))
            : $user->name;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'usuario_id' => $user->usuario_id,
                    'username' => $user->name,
                    'full_name' => $fullName,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->codigo,
                    'role_description' => $user->role?->descripcion,
                ]
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}