<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'usuarios';

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
}