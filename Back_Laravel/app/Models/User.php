<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /**
     * HasApiTokens:
     * Permite generar tokens con Sanctum, por ejemplo:
     * $user->createToken('auth_token')->plainTextToken;
     *
     * HasFactory:
     * Habilita factories para pruebas o seeders.
     *
     * Notifiable:
     * Permite enviar notificaciones al usuario.
     */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'role_id',
        'name',
        'email',
        'password',
    ];

    /**
     * Campos que no deben exponerse en respuestas JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversión automática de tipos.
     *
     * password => hashed
     * Laravel aplicará hash al asignar password si corresponde.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relación con la tabla roles.
     * users.role_id -> roles.id
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * Relación con la tabla usuarios.
     * users.usuario_id -> usuarios.id
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}