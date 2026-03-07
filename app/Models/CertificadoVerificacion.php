<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'fecha_emision'                 => 'date',
        'fecha_inicio_verificacion'     => 'date',
        'fecha_fin_verificacion'        => 'date',
        'tabla_cumplimiento'            => 'array',
        'hallazgos'                     => 'array',
        'recomendaciones_especificas'   => 'array',
        'archivos_adicionales'          => 'array',
        'vigente'                       => 'boolean',
        'fecha_caducidad'               => 'date',
        'requiere_verificacion_extraordinaria' => 'boolean',
    ];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }
}