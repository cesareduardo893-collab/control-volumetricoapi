<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alarma extends Model
{
    use HasFactory;

    protected $table = 'alarmas';

    protected $fillable = [
        'numero_registro',
        'fecha_hora',
        'componente_tipo',
        'componente_id',
        'componente_identificador',
        'tipo_alarma_id',
        'gravedad',
        'descripcion',
        'datos_contexto',
        'diferencia_detectada',
        'porcentaje_diferencia',
        'limite_permitido',
        'diagnostico_automatico',
        'recomendaciones',
        'atendida',
        'fecha_atencion',
        'atendida_por',
        'acciones_tomadas',
        'estado_atencion',
        'requiere_atencion_inmediata',
        'fecha_limite_atencion',
        'historial_cambios_estado',
    ];

    protected $casts = [
        'fecha_hora'                => 'datetime',
        'datos_contexto'            => 'array',
        'diferencia_detectada'      => 'decimal:4',
        'porcentaje_diferencia'     => 'decimal:4',
        'limite_permitido'          => 'decimal:4',
        'diagnostico_automatico'    => 'array',
        'recomendaciones'           => 'array',
        'atendida'                  => 'boolean',
        'fecha_atencion'            => 'datetime',
        'requiere_atencion_inmediata' => 'boolean',
        'fecha_limite_atencion'     => 'datetime',
        'historial_cambios_estado'  => 'array',
    ];

    public function atendidaPor()
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    public function tipoAlarma()
    {
        return $this->belongsTo(CatalogoValor::class, 'tipo_alarma_id');
    }
}
