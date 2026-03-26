<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReporteSat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reportes_sat';

    protected $fillable = [
        'instalacion_id',
        'usuario_genera_id',
        'folio',
        'periodo',
        'tipo_reporte',
        'ruta_xml',
        'ruta_pdf',
        'hash_sha256',
        'cadena_original',
        'sello_digital',
        'certificado_sat',
        'fecha_firma',
        'datos_firma',
        'folio_firma',
        'estado',
        'fecha_generacion',
        'fecha_envio',
        'acuse_sat',
        'mensaje_respuesta',
        'detalle_respuesta',
        'datos_reporte',
        'detalle_errores',
        'numero_intentos',
    ];

    protected $casts = [
        'fecha_firma' => 'datetime',
        'datos_firma' => 'array',
        'fecha_generacion' => 'date',
        'fecha_envio' => 'date',
        'detalle_respuesta' => 'array',
        'datos_reporte' => 'array',
        'detalle_errores' => 'array',
        'numero_intentos' => 'integer',
    ];

    public const TIPO_REPORTE_MENSUAL = 'MENSUAL';
    public const TIPO_REPORTE_ANUAL = 'ANUAL';
    public const TIPO_REPORTE_ESPECIAL = 'ESPECIAL';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_GENERADO = 'GENERADO';
    public const ESTADO_FIRMADO = 'FIRMADO';
    public const ESTADO_ENVIADO = 'ENVIADO';
    public const ESTADO_ACEPTADO = 'ACEPTADO';
    public const ESTADO_RECHAZADO = 'RECHAZADO';
    public const ESTADO_ERROR = 'ERROR';
    public const ESTADO_REQUIERE_REENVIO = 'REQUIERE_REENVIO';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->folio)) {
                $model->folio = self::generarFolioSecuencial();
            }
        });
    }

    /**
     * Generar folio secuencial automático
     */
    public static function generarFolioSecuencial(): string
    {
        $ultimoFolio = self::where('folio', 'like', 'RPT-%')
            ->orderBy('id', 'desc')
            ->value('folio');

        if ($ultimoFolio) {
            $numero = intval(str_replace('RPT-', '', $ultimoFolio)) + 1;
        } else {
            $numero = 1;
        }

        return 'RPT-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function usuarioGenera()
    {
        return $this->belongsTo(User::class, 'usuario_genera_id');
    }
}