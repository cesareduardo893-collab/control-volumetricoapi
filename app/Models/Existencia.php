<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Existencia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'existencias';

    protected $fillable = [
        'numero_registro',
        'tanque_id',
        'producto_id',
        'fecha',
        'hora',
        'volumen_medido',
        'temperatura',
        'presion',
        'densidad',
        'volumen_corregido',
        'factor_correccion_temperatura',
        'factor_correccion_presion',
        'volumen_disponible',
        'volumen_agua',
        'volumen_sedimentos',
        'volumen_inicial_dia',
        'volumen_calculado',
        'diferencia_volumen',
        'porcentaje_diferencia',
        'detalle_calculo',
        'movimientos_dia',
        'tipo_registro',
        'tipo_movimiento',
        'documento_referencia',
        'rfc_contraparte',
        'observaciones',
        'usuario_registro_id',
        'usuario_valida_id',
        'fecha_validacion',
        'estado',
    ];

    protected $casts = [
        'fecha'                        => 'date',
        'hora'                         => 'string',
        'volumen_medido'                => 'decimal:4',
        'temperatura'                   => 'decimal:2',
        'presion'                       => 'decimal:3',
        'densidad'                      => 'decimal:4',
        'volumen_corregido'             => 'decimal:4',
        'factor_correccion_temperatura' => 'decimal:6',
        'factor_correccion_presion'     => 'decimal:6',
        'volumen_disponible'            => 'decimal:4',
        'volumen_agua'                  => 'decimal:4',
        'volumen_sedimentos'            => 'decimal:4',
        'volumen_inicial_dia'           => 'decimal:4',
        'volumen_calculado'             => 'decimal:4',
        'diferencia_volumen'            => 'decimal:4',
        'porcentaje_diferencia'         => 'decimal:4',
        'detalle_calculo'                => 'array',
        'movimientos_dia'                => 'array',
        'fecha_validacion'               => 'datetime',
    ];

    public function tanque()
    {
        return $this->belongsTo(Tanque::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    public function usuarioValida()
    {
        return $this->belongsTo(User::class, 'usuario_valida_id');
    }
}