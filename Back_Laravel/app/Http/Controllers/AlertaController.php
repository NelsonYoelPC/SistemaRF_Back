<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\PersonaInteres;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'camara_id' => 'required|integer',
            'imagen_captura' => 'nullable|string'
        ]);

        $persona = PersonaInteres::where('usuario_id', $validated['usuario_id'])->first();
        
        if (!$persona) {
            return response()->json(['status' => false, 'message' => 'El usuario no está en la lista de Personas de Interés'], 404);
        }

        // Mapear prioridad (Baja, Media, Alta) a nivel de riesgo (Bajo, Medio, Alto)
        $prioridadMap = [
            'Baja' => 'Bajo',
            'Media' => 'Medio',
            'Alta' => 'Alto'
        ];
        
        $nivel_riesgo = $prioridadMap[$persona->prioridad] ?? 'Medio';

        $alerta = Alerta::create([
            'persona_interes_id' => $persona->id,
            'camara_id' => $validated['camara_id'],
            'nivel_riesgo' => $nivel_riesgo,
            'estado' => 'nueva',
            'imagen_captura' => $validated['imagen_captura'] ?? null,
            'fecha_deteccion' => now()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Alerta registrada en el historial correctamente',
            'data' => $alerta
        ], 201);
    }
}
