<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoDia extends Model
{
    use HasFactory;

    protected $table = 'movimientos_dia';

    protected $fillable = [
        'existencia_id',
        'tipo_movimiento',
        'volumen',
        'temperatura',
        'presion',
        'densidad',
        'volumen_corregido',
        'documento_referencia',
        'rfc_contraparte',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'volumen' => 'decimal:4',
        'temperatura' => 'decimal:2',
        'presion' => 'decimal:3',
        'densidad' => 'decimal:4',
        'volumen_corregido' => 'decimal:4',
    ];

    public const TIPO_MOVIMIENTO_INICIAL = 'INICIAL';
    public const TIPO_MOVIMIENTO_RECEPCION = 'RECEPCION';
    public const TIPO_MOVIMIENTO_ENTREGA = 'ENTREGA';
    public const TIPO_MOVIMIENTO_VENTA = 'VENTA';
    public const TIPO_MOVIMIENTO_TRASPASO = 'TRASPASO';
    public const TIPO_MOVIMIENTO_AJUSTE = 'AJUSTE';
    public const TIPO_MOVIMIENTO_INVENTARIO = 'INVENTARIO';

    public function existencia()
    {
        return $this->belongsTo(Existencia::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}