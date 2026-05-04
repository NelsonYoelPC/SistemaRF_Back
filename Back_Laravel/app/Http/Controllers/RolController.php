<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json($roles);
    }

    public function show(int $id): JsonResponse
    {
        $rol = Role::find($id);

        if (!$rol) {
            return response()->json([
                'message' => 'Rol no encontrado.'
            ], 404);
        }

        return response()->json($rol);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string|max:100|unique:roles,codigo',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'required|integer|in:0,1',
        ]);

        $rol = Role::create([
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
        ]);

        return response()->json([
            'message' => 'Rol registrado correctamente.',
            'data' => $rol
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rol = Role::find($id);

        if (!$rol) {
            return response()->json([
                'message' => 'Rol no encontrado.'
            ], 404);
        }

        $request->validate([
            'codigo' => 'required|string|max:100|unique:roles,codigo,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'required|integer|in:0,1',
        ]);

        $rol->update([
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
        ]);

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => $rol
        ]);
    }

    public function updateEstado(Request $request, int $id): JsonResponse
    {
        $rol = Role::find($id);

        if (!$rol) {
            return response()->json([
                'message' => 'Rol no encontrado.'
            ], 404);
        }

        $request->validate([
            'estado' => 'required|integer|in:0,1',
        ]);

        $rol->estado = $request->estado;
        $rol->save();

        return response()->json([
            'message' => 'Estado del rol actualizado correctamente.',
            'data' => $rol
        ]);
    }
}