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

    public function store(Request $request)
    {
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
            'foto_principal' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($request->boolean('tiene_acceso')) {
            $rules['username'] = ['required', 'string', 'max:50', Rule::unique('users', 'name')];
            $rules['email'] = ['required', 'email', 'max:150', Rule::unique('users', 'email')];
            $rules['password'] = ['required', 'string', 'min:8', 'max:100'];
            $rules['role_id'] = ['required', 'integer', 'exists:roles,id'];
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            $usuario = Usuario::create([
                'nombres' => trim($validated['nombres']),
                'apellido_paterno' => trim($validated['apellido_paterno']),
                'apellido_materno' => $validated['apellido_materno'] ?? null,
                'tipo_documento' => trim($validated['tipo_documento']),
                'numero_documento' => trim($validated['numero_documento']),
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'cargo' => $validated['cargo'] ?? null,
                'estado' => $validated['estado'],
            ]);

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

            $foto = null;
            if ($request->hasFile('foto_principal')) {
                $file = $request->file('foto_principal');
                $path = $file->store('usuarios', 'public');

                $foto = Foto::create([
                    'usuario_id' => $usuario->id,
                    'nombre_original' => $file->getClientOriginalName(),
                    'nombre_archivo' => basename($path),
                    'ruta' => 'storage/' . $path,
                    'extension' => $file->getClientOriginalExtension(),
                    'mime_type' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'tipo_foto' => 'PERFIL',
                    'es_principal' => 1,
                    'estado' => 1,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'data' => [
                    'usuario_id' => $usuario->id,
                    'user_id' => $user?->id,
                    'foto_id' => $foto?->id,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo registrar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}