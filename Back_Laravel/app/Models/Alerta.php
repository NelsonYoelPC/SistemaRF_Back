<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    use HasFactory;
    
    protected $table = 'alertas';
    public $timestamps = true;

    protected $fillable = [
        'persona_interes_id',
        'camara_id',
        'nivel_riesgo',
        'estado',
        'imagen_captura',
        'fecha_deteccion'
    ];

    public function personaInteres()
    {
        return $this->belongsTo(PersonaInteres::class, 'persona_interes_id');
    }
}
