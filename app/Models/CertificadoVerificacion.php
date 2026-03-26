<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CertificadoVerificacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'certificados_verificacion';

    protected $fillable = [
        'folio',
        'contribuyente_id',
        'proveedor_rfc',
        'proveedor_nombre',
        'fecha_emision',
        'fecha_inicio_verificacion',
        'fecha_fin_verificacion',
        'resultado',
        'tabla_cumplimiento',
        'hallazgos',
        'recomendaciones_especificas',
        'observaciones',
        'recomendaciones',
        'archivo_pdf',
        'archivo_xml',
        'archivo_json',
        'archivos_adicionales',
        'vigente',
        'fecha_caducidad',
        'requiere_verificacion_extraordinaria',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_inicio_verificacion' => 'date',
        'fecha_fin_verificacion' => 'date',
        'tabla_cumplimiento' => 'array',
        'hallazgos' => 'array',
        'recomendaciones_especificas' => 'array',
        'archivos_adicionales' => 'array',
        'vigente' => 'boolean',
        'fecha_caducidad' => 'date',
        'requiere_verificacion_extraordinaria' => 'boolean',
    ];

    public const RESULTADO_ACREDITADO = 'acreditado';
    public const RESULTADO_NO_ACREDITADO = 'no_acreditado';

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
        $ultimoFolio = self::where('folio', 'like', 'CERT-%')
            ->orderBy('id', 'desc')
            ->value('folio');

        if ($ultimoFolio) {
            $numero = intval(str_replace('CERT-', '', $ultimoFolio)) + 1;
        } else {
            $numero = 1;
        }

        return 'CERT-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }
}