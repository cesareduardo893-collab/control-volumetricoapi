<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RegistroVolumetrico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'registros_volumetricos';

    protected $fillable = [
        'numero_registro',
        'instalacion_id',
        'tanque_id',
        'medidor_id',
        'producto_id',
        'usuario_registro_id',
        'usuario_valida_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'volumen_inicial',
        'volumen_final',
        'volumen_operacion',
        'temperatura_inicial',
        'temperatura_final',
        'presion_inicial',
        'presion_final',
        'densidad',
        'volumen_corregido',
        'factor_correccion',
        'detalle_correccion',
        'masa',
        'poder_calorifico',
        'energia_total',
        'tipo_registro',
        'operacion',
        'rfc_contraparte',
        'documento_fiscal_uuid',
        'folio_fiscal',
        'tipo_cfdi',
        'dictamen_id',
        'estado',
        'fecha_validacion',
        'validaciones_realizadas',
        'inconsistencias_detectadas',
        'porcentaje_diferencia',
        'observaciones',
        'errores',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_fin' => 'string',
        'volumen_inicial' => 'decimal:4',
        'volumen_final' => 'decimal:4',
        'volumen_operacion' => 'decimal:4',
        'temperatura_inicial' => 'decimal:2',
        'temperatura_final' => 'decimal:2',
        'presion_inicial' => 'decimal:3',
        'presion_final' => 'decimal:3',
        'densidad' => 'decimal:4',
        'volumen_corregido' => 'decimal:4',
        'factor_correccion' => 'decimal:6',
        'detalle_correccion' => 'array',
        'masa' => 'decimal:4',
        'poder_calorifico' => 'decimal:4',
        'energia_total' => 'decimal:4',
        'fecha_validacion' => 'datetime',
        'validaciones_realizadas' => 'array',
        'inconsistencias_detectadas' => 'array',
        'porcentaje_diferencia' => 'decimal:4',
        'errores' => 'array',
    ];

    public const TIPO_REGISTRO_OPERACION = 'operacion';
    public const TIPO_REGISTRO_ACUMULADO = 'acumulado';
    public const TIPO_REGISTRO_EXISTENCIAS = 'existencias';

    public const OPERACION_RECEPCION = 'recepcion';
    public const OPERACION_ENTREGA = 'entrega';
    public const OPERACION_INVENTARIO_INICIAL = 'inventario_inicial';
    public const OPERACION_INVENTARIO_FINAL = 'inventario_final';
    public const OPERACION_VENTA = 'venta';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PROCESADO = 'PROCESADO';
    public const ESTADO_VALIDADO = 'VALIDADO';
    public const ESTADO_ERROR = 'ERROR';
    public const ESTADO_CANCELADO = 'CANCELADO';
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
        $ultimoNumero = self::where('numero_registro', 'like', 'RV-%')
            ->orderBy('id', 'desc')
            ->value('numero_registro');

        if ($ultimoNumero) {
            $numero = intval(str_replace('RV-', '', $ultimoNumero)) + 1;
        } else {
            $numero = 1;
        }

        return 'RV-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function tanque()
    {
        return $this->belongsTo(Tanque::class);
    }

    public function medidor()
    {
        return $this->belongsTo(Medidor::class);
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

    public function dictamen()
    {
        return $this->belongsTo(Dictamen::class);
    }

    public function cfdis()
    {
        return $this->hasMany(Cfdi::class, 'registro_volumetrico_id');
    }

    public function pedimentos()
    {
        return $this->hasMany(Pedimento::class, 'registro_volumetrico_id');
    }
}