<?php

namespace App\Http\Controllers;

use App\Models\PersonaInteres;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonaInteresController extends Controller
{
    /**
     * Listar personas de interés activas
     */
    public function index()
    {
        // Traemos los activos con la relación del usuario y su foto principal
        $objetivos = PersonaInteres::with(['usuario.fotos' => function($q) {
            $q->where('es_principal', 1);
        }])
        ->where('activo', 1)
        ->orderByDesc('created_at')
        ->get();

        return response()->json([
            'status' => true,
            'data' => $objetivos
        ], 200);
    }

    /**
     * Agregar un usuario a vigilancia activa
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'prioridad' => 'required|in:Baja,Media,Alta',
            'motivo' => 'nullable|string|max:255',
            'motor' => 'nullable|integer|in:0,1',
        ]);

        // 1. Verificar si ya está activo
        $existe = PersonaInteres::where('usuario_id', $validated['usuario_id'])
                                ->where('activo', 1)
                                ->first();
        
        if ($existe) {
            return response()->json([
                'status' => false,
                'message' => 'Este usuario ya se encuentra en la lista de vigilancia activa.'
            ], 422);
        }

        // 2. Si existía pero estaba inactivo, lo reactivamos para no duplicar filas
        $inactivo = PersonaInteres::where('usuario_id', $validated['usuario_id'])
                                  ->where('activo', 0)
                                  ->first();

        if ($inactivo) {
            $inactivo->update([
                'activo' => 1,
                'prioridad' => $validated['prioridad'],
                'motivo' => $validated['motivo'],
                'motor' => $request->motor ?? 0,
                'creado_por' => $request->creado_por ?? null
            ]);
            
            return response()->json([
                'status' => true,
                'message' => 'Objetivo reactivado en la lista de vigilancia.',
                'data' => $inactivo
            ]);
        }

        // 3. Si es nuevo, lo creamos
        $persona = PersonaInteres::create([
            'usuario_id' => $validated['usuario_id'],
            'prioridad' => $validated['prioridad'],
            'motivo' => $validated['motivo'],
            'motor' => $request->motor ?? 0,
            'activo' => 1,
            'creado_por' => $request->creado_por ?? null
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Usuario añadido a la lista de vigilancia.',
            'data' => $persona
        ], 201);
    }

    /**
     * Actualizar dinámicamente campos (motor, activo, prioridad)
     */
    public function update(Request $request, $id)
    {
        $persona = PersonaInteres::find($id);

        if (!$persona) {
            return response()->json(['status' => false, 'message' => 'Registro no encontrado'], 404);
        }

        $persona->update($request->only(['motor', 'activo', 'prioridad', 'motivo']));

        return response()->json([
            'status' => true,
            'message' => 'Vigilancia actualizada correctamente.',
            'data' => $persona
        ]);
    }

    /**
     * Quitar de vigilancia (Desactivar lógicamente)
     */
    public function destroy($id)
    {
        $persona = PersonaInteres::where('usuario_id', $id)
                                ->where('activo', 1)
                                ->first();

        if (!$persona) {
            return response()->json([
                'status' => false,
                'message' => 'El usuario no se encuentra en la lista de vigilancia activa.'
            ], 404);
        }

        // CAMBIO DE ESTADO (NO BORRADO)
        $persona->update(['activo' => 0]);

        return response()->json([
            'status' => true,
            'message' => 'Vigilancia desactivada. El registro se conserva en el historial.'
        ]);
    }
}
