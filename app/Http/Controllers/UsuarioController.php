<?php

namespace App\Http\Controllers;

use App\Models\Foto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /* =========================================================
       LISTAR USUARIOS
       - Devuelve datos del usuario
       - Devuelve datos de acceso si existen
       - Mantiene la estructura actual del frontend
       ========================================================= */
    public function index()
    {
        $usuarios = DB::table('usuarios as u')
            ->leftJoin('users as us', 'us.usuario_id', '=', 'u.id')
            ->leftJoin('roles as r', 'r.id', '=', 'us.role_id')
            ->selectRaw("
                u.id AS usuario_id,
                TRIM(CONCAT(
                    u.nombres, ' ',
                    u.apellido_paterno, ' ',
                    COALESCE(u.apellido_materno, '')
                )) AS nombre_completo,
                u.tipo_documento,
                u.numero_documento,
                u.telefono,
                u.cargo,
                u.estado AS estado_usuario,
                us.id AS user_id,
                us.name AS username,
                us.email,
                r.codigo AS rol_codigo,
                r.descripcion AS rol_descripcion
            ")
            ->orderByDesc('u.id')
            ->get();

        return response()->json($usuarios, 200);
    }

    /* =========================================================
       REGISTRAR USUARIO
       - Registra datos personales en tabla usuarios
       - Registra acceso al sistema en tabla users
       - Registra hasta 4 fotos en base64 en tabla fotos
       - Todo se guarda en una transacción
       ========================================================= */
    public function store(Request $request)
    {
        /* =====================================================
           REGLAS BASE DE VALIDACIÓN
           ===================================================== */
        $rules = [
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'tipo_documento' => ['required', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:usuarios,numero_documento'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'estado' => ['required', 'boolean'],
            'tiene_acceso' => ['required', 'boolean'],

            /* =================================================
               FOTOS EN BASE64
               - El frontend enviará un arreglo de fotos
               - Se permite hasta 4 fotos por usuario
               ================================================= */
            'fotos_principales_base64' => ['nullable', 'array', 'max:4'],
            'fotos_principales_base64.*' => ['nullable', 'string'],
        ];

        /* =====================================================
           VALIDACIÓN CONDICIONAL DE ACCESO AL SISTEMA
           - Solo si tiene_acceso = true
           ===================================================== */
        if ($request->boolean('tiene_acceso')) {
            $rules['username'] = ['required', 'string', 'max:50', Rule::unique('users', 'name')];
            $rules['email'] = ['required', 'email', 'max:150', Rule::unique('users', 'email')];
            $rules['password'] = ['required', 'string', 'min:8', 'max:100'];
            $rules['role_id'] = ['required', 'integer', 'exists:roles,id'];
        }

        /* =====================================================
           EJECUTAR VALIDACIÓN
           ===================================================== */
        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            /* =================================================
               1. REGISTRAR USUARIO EN TABLA usuarios
               ================================================= */
            $usuario = Usuario::create([
                'nombres' => trim($validated['nombres']),
                'apellido_paterno' => trim($validated['apellido_paterno']),
                'apellido_materno' => isset($validated['apellido_materno']) && $validated['apellido_materno'] !== ''
                    ? trim($validated['apellido_materno'])
                    : null,
                'tipo_documento' => trim($validated['tipo_documento']),
                'numero_documento' => trim($validated['numero_documento']),
                'telefono' => isset($validated['telefono']) && $validated['telefono'] !== ''
                    ? trim($validated['telefono'])
                    : null,
                'direccion' => isset($validated['direccion']) && $validated['direccion'] !== ''
                    ? trim($validated['direccion'])
                    : null,
                'cargo' => isset($validated['cargo']) && $validated['cargo'] !== ''
                    ? trim($validated['cargo'])
                    : null,
                'estado' => (int) $validated['estado'],
            ]);

            /* =================================================
               2. REGISTRAR ACCESO AL SISTEMA EN TABLA users
               - Solo si tiene_acceso = true
               ================================================= */
            $user = null;

            if ($request->boolean('tiene_acceso')) {
                $user = User::create([
                    'usuario_id' => $usuario->id,
                    'role_id' => (int) $validated['role_id'],
                    'name' => trim($validated['username']),
                    'email' => trim($validated['email']),
                    'password' => Hash::make($validated['password']),
                ]);
            }

            /* =================================================
               3. REGISTRAR FOTOS EN TABLA fotos
               - Se guarda una fila por cada foto
               - La primera foto se marca como principal
               - Se usa orden 1, 2, 3, 4
               ================================================= */
            $fotoIds = [];
            $fotosBase64 = $validated['fotos_principales_base64'] ?? [];

            foreach ($fotosBase64 as $index => $fotoBase64) {
                /* =============================================
                   IGNORAR VALORES VACÍOS
                   ============================================= */
                if (empty($fotoBase64)) {
                    continue;
                }
            
                /* =============================================
                   VALIDAR QUE EL CONTENIDO SEA UNA IMAGEN BASE64
                   ============================================= */
                if (!$this->esBase64ImagenValida($fotoBase64)) {
                    DB::rollBack();
                
                    return response()->json([
                        'message' => 'Una de las fotos no tiene un formato base64 válido.'
                    ], 422);
                }
            
                /* =============================================
                   LIMPIAR PREFIJO:
                   data:image/jpeg;base64,
                   data:image/png;base64,
                   etc.
                   ============================================= */
                $base64Limpio = $this->obtenerContenidoBase64($fotoBase64);
            
                /* =============================================
                   REGISTRAR FOTO EN TABLA fotos
                   - es_principal = 1 solo para la primera foto
                   - base64 = solo contenido limpio
                   ============================================= */
                $foto = Foto::create([
                    'usuario_id' => $usuario->id,
                    'es_principal' => $index === 0 ? 1 : 0,
                    'base64' => $base64Limpio,
                    'orden' => $index + 1,
                    'estado' => 1,
                ]);
            
                $fotoIds[] = $foto->id;
            }

            /* =================================================
               4. CONFIRMAR TRANSACCIÓN
               ================================================= */
            DB::commit();

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'data' => [
                    'usuario_id' => $usuario->id,
                    'user_id' => $user?->id,
                    'foto_ids' => $fotoIds,
                ]
            ], 201);

        } catch (\Throwable $e) {
            /* =================================================
               REVERTIR TODO SI OCURRE UN ERROR
               ================================================= */
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo registrar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/* =========================================================
   VALIDAR BASE64 DE IMAGEN
   - Acepta jpeg, jpg, png y webp
   - Valida prefijo data:image/...;base64,
   - Valida que el contenido pueda decodificarse
   ========================================================= */
private function esBase64ImagenValida(string $valor): bool
{
    /* =====================================================
       VALIDAR FORMATO DEL PREFIJO
       ===================================================== */
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $valor)) {
        return false;
    }

    /* =====================================================
       SEPARAR CABECERA Y CONTENIDO BASE64
       ===================================================== */
    $partes = explode(',', $valor, 2);

    if (count($partes) !== 2) {
        return false;
    }

    /* =====================================================
       VALIDAR QUE EL CONTENIDO SEA BASE64 CORRECTO
       ===================================================== */
    return base64_decode($partes[1], true) !== false;
}

    /* =========================================================
       OBTENER SOLO EL CONTENIDO BASE64
       - Elimina el prefijo:
         data:image/jpeg;base64,
         data:image/png;base64,
       - Devuelve solo la cadena base64 pura
       ========================================================= */
    private function obtenerContenidoBase64(string $valor): string
    {
        $partes = explode(',', $valor, 2);

        return $partes[1] ?? $valor;
    }
/* =========================================================
   OBTENER DETALLE DE UN USUARIO
   - Devuelve datos personales
   - Devuelve acceso al sistema
   - Devuelve fotos
   ========================================================= */
public function show(int $id)
{
    $usuario = Usuario::with([
        'user:id,usuario_id,role_id,name,email',
        'fotos' => function ($query) {
            $query->where('estado', 1)
                  ->orderBy('orden');
        }
    ])->find($id);

    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado.'
        ], 404);
    }

    return response()->json([
        'data' => $usuario
    ], 200);
}
/* =========================================================
   ACTUALIZAR USUARIO
   - Actualiza datos personales
   - Actualiza acceso al sistema
   - Reemplaza fotos si llegan nuevas
   ========================================================= */
public function update(Request $request, int $id)
{
    $usuario = Usuario::find($id);

    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado.'
        ], 404);
    }

    $rules = [
        'nombres' => ['required', 'string', 'max:100'],
        'apellido_paterno' => ['required', 'string', 'max:100'],
        'apellido_materno' => ['nullable', 'string', 'max:100'],
        'tipo_documento' => ['required', 'string', 'max:20'],
        'numero_documento' => [
            'required',
            'string',
            'max:20',
            Rule::unique('usuarios', 'numero_documento')->ignore($usuario->id)
        ],
        'telefono' => ['nullable', 'string', 'max:20'],
        'direccion' => ['nullable', 'string', 'max:200'],
        'cargo' => ['nullable', 'string', 'max:100'],
        'estado' => ['required', 'boolean'],
        'tiene_acceso' => ['required', 'boolean'],
        'fotos_principales_base64' => ['nullable', 'array', 'max:4'],
        'fotos_principales_base64.*' => ['nullable', 'string'],
    ];

    $userActual = User::where('usuario_id', $usuario->id)->first();

    if ($request->boolean('tiene_acceso')) {
        $rules['username'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('users', 'name')->ignore($userActual?->id)
        ];
        $rules['email'] = [
            'required',
            'email',
            'max:150',
            Rule::unique('users', 'email')->ignore($userActual?->id)
        ];
        $rules['password'] = ['nullable', 'string', 'min:8', 'max:100'];
        $rules['role_id'] = ['required', 'integer', 'exists:roles,id'];
    }

    $validated = $request->validate($rules);

    DB::beginTransaction();

    try {
        /* =============================================
           ACTUALIZAR USUARIO
           ============================================= */
        $usuario->update([
            'nombres' => trim($validated['nombres']),
            'apellido_paterno' => trim($validated['apellido_paterno']),
            'apellido_materno' => !empty($validated['apellido_materno']) ? trim($validated['apellido_materno']) : null,
            'tipo_documento' => trim($validated['tipo_documento']),
            'numero_documento' => trim($validated['numero_documento']),
            'telefono' => !empty($validated['telefono']) ? trim($validated['telefono']) : null,
            'direccion' => !empty($validated['direccion']) ? trim($validated['direccion']) : null,
            'cargo' => !empty($validated['cargo']) ? trim($validated['cargo']) : null,
            'estado' => (int) $validated['estado'],
        ]);

        /* =============================================
           ACTUALIZAR O CREAR USUARIO DE ACCESO
           ============================================= */
        if ($request->boolean('tiene_acceso')) {
            if ($userActual) {
                $dataUser = [
                    'role_id' => (int) $validated['role_id'],
                    'name' => trim($validated['username']),
                    'email' => trim($validated['email']),
                ];
            
                /* =========================================
                   SOLO ACTUALIZAR CONTRASEÑA SI EL USUARIO
                   ESCRIBIÓ UNA NUEVA
                   ========================================= */
                if (!empty($validated['password'])) {
                    $dataUser['password'] = Hash::make($validated['password']);
                }
            
                $userActual->update($dataUser);
            } else {
                User::create([
                    'usuario_id' => $usuario->id,
                    'role_id' => (int) $validated['role_id'],
                    'name' => trim($validated['username']),
                    'email' => trim($validated['email']),
                    'password' => Hash::make($validated['password']),
                ]);
            }
        }
        /* =============================================
           REEMPLAZAR FOTOS SI LLEGAN EN EL REQUEST
           ============================================= */
        $fotosBase64 = $validated['fotos_principales_base64'] ?? [];

        Foto::where('usuario_id', $usuario->id)->delete();

        foreach ($fotosBase64 as $index => $fotoBase64) {
            if (empty($fotoBase64)) {
                continue;
            }

            Foto::create([
                'usuario_id' => $usuario->id,
                'es_principal' => $index === 0 ? 1 : 0,
                'base64' => $fotoBase64,
                'orden' => $index + 1,
                'estado' => 1,
            ]);
        }

        DB::commit();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.'
        ], 200);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'message' => 'No se pudo actualizar el usuario.',
            'error' => $e->getMessage()
        ], 500);
    }
}
/* =========================================================
   ACTUALIZAR ESTADO DEL USUARIO
   - Activa o desactiva el usuario en la tabla usuarios
   - Recibe el estado desde el frontend
   ========================================================= */
public function updateEstado(Request $request, int $id)
{
    /* =====================================================
       VALIDAR ESTADO
       ===================================================== */
    $validated = $request->validate([
        'estado' => ['required', 'boolean'],
    ]);

    /* =====================================================
       BUSCAR USUARIO
       ===================================================== */
    $usuario = Usuario::find($id);

    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado.'
        ], 404);
    }

    try {
        /* =================================================
           ACTUALIZAR ESTADO EN BD
           ================================================= */
        $usuario->estado = (int) $validated['estado'];
        $usuario->save();

        return response()->json([
            'message' => $usuario->estado ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.',
            'data' => [
                'usuario_id' => $usuario->id,
                'estado' => (bool) $usuario->estado,
            ]
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'No se pudo actualizar el estado del usuario.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}