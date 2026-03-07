<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'fecha_firma'       => 'datetime',
        'datos_firma'       => 'array',
        'fecha_generacion'  => 'date',
        'fecha_envio'       => 'date',
        'detalle_respuesta' => 'array',
        'datos_reporte'     => 'array',
        'detalle_errores'   => 'array',
    ];

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function usuarioGenera()
    {
        return $this->belongsTo(User::class, 'usuario_genera_id');
    }
}