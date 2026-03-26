<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'fecha' => 'date',
        'hora' => 'string',
        'volumen_medido' => 'decimal:4',
        'temperatura' => 'decimal:2',
        'presion' => 'decimal:3',
        'densidad' => 'decimal:4',
        'volumen_corregido' => 'decimal:4',
        'factor_correccion_temperatura' => 'decimal:6',
        'factor_correccion_presion' => 'decimal:6',
        'volumen_disponible' => 'decimal:4',
        'volumen_agua' => 'decimal:4',
        'volumen_sedimentos' => 'decimal:4',
        'volumen_inicial_dia' => 'decimal:4',
        'volumen_calculado' => 'decimal:4',
        'diferencia_volumen' => 'decimal:4',
        'porcentaje_diferencia' => 'decimal:4',
        'detalle_calculo' => 'array',
        'fecha_validacion' => 'datetime',
    ];

    public const TIPO_REGISTRO_INICIAL = 'inicial';
    public const TIPO_REGISTRO_OPERACION = 'operacion';
    public const TIPO_REGISTRO_FINAL = 'final';

    public const TIPO_MOVIMIENTO_INICIAL = 'INICIAL';
    public const TIPO_MOVIMIENTO_RECEPCION = 'RECEPCION';
    public const TIPO_MOVIMIENTO_ENTREGA = 'ENTREGA';
    public const TIPO_MOVIMIENTO_VENTA = 'VENTA';
    public const TIPO_MOVIMIENTO_TRASPASO = 'TRASPASO';
    public const TIPO_MOVIMIENTO_AJUSTE = 'AJUSTE';
    public const TIPO_MOVIMIENTO_INVENTARIO = 'INVENTARIO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_VALIDADO = 'VALIDADO';
    public const ESTADO_EN_REVISION = 'EN_REVISION';
    public const ESTADO_CON_ALARMA = 'CON_ALARMA';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->numero_registro)) {
                $model->numero_registro = self::generarNumeroRegistroSecuencial();
            }
        });
    }

    /**
     * Generar número de registro secuencial automático
     */
    public static function generarNumeroRegistroSecuencial(): string
    {
        $ultimoNumero = self::where('numero_registro', 'like', 'EX-%')
            ->orderBy('id', 'desc')
            ->value('numero_registro');

        if ($ultimoNumero) {
            $numero = intval(str_replace('EX-', '', $ultimoNumero)) + 1;
        } else {
            $numero = 1;
        }

        return 'EX-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

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

    public function movimientosDia()
    {
        return $this->hasMany(MovimientoDia::class, 'existencia_id');
    }
}