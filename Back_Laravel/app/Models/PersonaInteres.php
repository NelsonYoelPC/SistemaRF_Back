<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonaInteres extends Model
{
    use HasFactory;

    protected $table = 'personas_interes';

    protected $fillable = [
        'usuario_id',
        'prioridad',
        'motivo',
        'motor',
        'activo',
        'creado_por'
    ];

    /**
     * Relación con el usuario vigilado
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Relación con el usuario que creó la alerta
     */
    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
