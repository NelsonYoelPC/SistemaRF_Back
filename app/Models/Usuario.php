<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Foto;

class Usuario extends Model
{
    use HasFactory;

    /* =========================================================
       TABLA ASOCIADA
       ========================================================= */
    protected $table = 'usuarios';

    /* =========================================================
       CAMPOS ASIGNABLES
       ========================================================= */
    protected $fillable = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'tipo_documento',
        'numero_documento',
        'telefono',
        'direccion',
        'cargo',
        'estado'
    ];

    /* =========================================================
       RELACIÓN: UN USUARIO TIENE MUCHAS FOTOS
       ========================================================= */
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'usuario_id');
    }
}