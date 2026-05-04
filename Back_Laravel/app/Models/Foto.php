<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    use HasFactory;

    /* =========================================================
       TABLA ASOCIADA
       ========================================================= */
    protected $table = 'fotos';

    /* =========================================================
       CAMPOS ASIGNABLES
       ========================================================= */
    protected $fillable = [
        'usuario_id',
        'es_principal',
        'base64',
        'orden',
        'estado'
    ];

    /* =========================================================
       RELACIÓN: UNA FOTO PERTENECE A UN USUARIO
       ========================================================= */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}