<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    protected $table = 'fotos';

    protected $fillable = [
        'usuario_id',
        'nombre_original',
        'nombre_archivo',
        'ruta',
        'extension',
        'mime_type',
        'tamano',
        'tipo_foto',
        'es_principal',
        'estado'
    ];
}