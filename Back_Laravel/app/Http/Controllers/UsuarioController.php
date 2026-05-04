<?php

namespace App\Http\Controllers;

use App\Models\Foto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;

class UsuarioController extends Controller
{
    /* =========================================================
       LISTAR USUARIOS
       ========================================================= */
    public function index()
    {
        $usuarios = DB::table('usuarios as u')
            ->leftJoin('users as us', 'us.usuario_id', '=', 'u.id')
            ->leftJoin('roles as r', 'r.id', '=', 'us.role_id')
            ->selectRaw("
                u.id AS usuario_id,
                TRIM(CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', COALESCE(u.apellido_materno, ''))) AS nombre_completo,
                u.tipo_documento, u.numero_documento, u.telefono, u.cargo, u.estado AS estado_usuario,
                us.id AS user_id, us.name AS username, us.email, r.codigo AS rol_codigo, r.descripcion AS rol_descripcion
            ")
            ->orderByDesc('u.id')
            ->get();

        return response()->json($usuarios, 200);
    }

    /* =========================================================
       REGISTRAR USUARIO
       ========================================================= */
    public function store(Request $request)
    {
        $rules = [
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:usuarios,numero_documento'],
            'tipo_documento' => ['required', 'string'],
            'estado' => ['required', 'boolean'],
            'tiene_acceso' => ['required', 'boolean'],
            'fotos_principales_base64' => ['nullable', 'array', 'max:4'],
        ];

        if ($request->boolean('tiene_acceso')) {
            $rules['username'] = ['required', 'unique:users,name'];
            $rules['email'] = ['required', 'email', 'unique:users,email'];
            $rules['password'] = ['required', 'min:8'];
            $rules['role_id'] = ['required', 'exists:roles,id'];
        }

        $validated = $request->validate($rules);
        DB::beginTransaction();

        try {
            $usuario = Usuario::create([
                'nombres' => trim($validated['nombres']),
                'apellido_paterno' => trim($validated['apellido_paterno']),
                'apellido_materno' => $request->apellido_materno ? trim($request->apellido_materno) : null,
                'tipo_documento' => trim($validated['tipo_documento']),
                'numero_documento' => trim($validated['numero_documento']),
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'cargo' => $request->cargo,
                'estado' => $validated['estado'],
            ]);

            if ($request->boolean('tiene_acceso')) {
                User::create([
                    'usuario_id' => $usuario->id,
                    'role_id' => $validated['role_id'],
                    'name' => trim($validated['username']),
                    'email' => trim($validated['email']),
                    'password' => Hash::make($validated['password']),
                ]);
            }

            if (!empty($validated['fotos_principales_base64'])) {
                foreach ($validated['fotos_principales_base64'] as $index => $base64) {
                    if ($base64) {
                        $base64Limpio = $this->obtenerContenidoBase64($base64);
                        Foto::create([
                            'usuario_id' => $usuario->id,
                            'es_principal' => $index === 0 ? 1 : 0,
                            'base64' => $base64Limpio,
                            'orden' => $index + 1,
                            'estado' => 1
                        ]);
                        $this->guardarFotoEnDisco($usuario->id, $base64Limpio, $index + 1);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Usuario registrado con éxito', 'id' => $usuario->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* =========================================================
       ACTUALIZAR USUARIO
       ========================================================= */
    public function update(Request $request, int $id)
    {
        $usuario = Usuario::findOrFail($id);
        $validated = $request->validate([
            'nombres' => ['required'],
            'apellido_paterno' => ['required'],
            'estado' => ['required', 'boolean'],
            'fotos_principales_base64' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $usuario->update([
                'nombres' => trim($validated['nombres']),
                'apellido_paterno' => trim($validated['apellido_paterno']),
                'apellido_materno' => $request->apellido_materno,
                'estado' => $validated['estado'],
                'cargo' => $request->cargo,
                'telefono' => $request->telefono,
            ]);

            if ($request->has('fotos_principales_base64')) {
                Foto::where('usuario_id', $usuario->id)->delete();
                // Opcional: Limpiar carpeta física antes de re-guardar
                $path = base_path('../IA_Python/base_datos/' . $usuario->id);
                if (File::exists($path)) { File::cleanDirectory($path); }

                foreach ($request->fotos_principales_base64 as $index => $base64) {
                    if ($base64) {
                        $base64Limpio = $this->obtenerContenidoBase64($base64);
                        Foto::create([
                            'usuario_id' => $usuario->id,
                            'es_principal' => $index === 0 ? 1 : 0,
                            'base64' => $base64Limpio,
                            'orden' => $index + 1,
                            'estado' => 1
                        ]);
                        $this->guardarFotoEnDisco($usuario->id, $base64Limpio, $index + 1);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Usuario actualizado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* =========================================================
       MOSTRAR USUARIO
       ========================================================= */
    public function show($id) {
        $usuario = Usuario::with(['user', 'fotos' => function($q) {
            $q->where('estado', 1)->orderBy('orden');
        }])->find($id);

        if (!$usuario) return response()->json(['message' => 'No encontrado'], 404);
        return response()->json(['data' => $usuario]);
    }

    /* =========================================================
       ACTUALIZAR ESTADO
       ========================================================= */
    public function updateEstado(Request $request, $id) {
        $u = Usuario::findOrFail($id);
        $u->update(['estado' => $request->estado]);
        return response()->json(['message' => 'Estado actualizado']);
    }

    /* =========================================================
       FUNCIONES AUXILIARES
       ========================================================= */
    private function obtenerContenidoBase64($valor) {
        if (str_contains($valor, ',')) {
            return explode(',', $valor)[1];
        }
        return $valor;
    }

    private function guardarFotoEnDisco($usuarioId, $base64, $orden) {
        try {
            $path = base_path('../IA_Python/base_datos/' . $usuarioId);
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
            File::put($path . '/' . $usuarioId . '_' . $orden . '.png', base64_decode($base64));
        } catch (\Exception $e) {
            \Log::error("Error IA Disk (User $usuarioId): " . $e->getMessage());
        }
    }
}