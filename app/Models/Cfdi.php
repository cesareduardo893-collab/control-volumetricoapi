<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cfdi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cfdi';

    protected $fillable = [
        'uuid',
        'rfc_emisor',
        'nombre_emisor',
        'rfc_receptor',
        'nombre_receptor',
        'tipo_operacion',
        'producto_id',
        'volumen',
        'unidad_medida',
        'precio_unitario',
        'subtotal',
        'iva',
        'ieps',
        'total',
        'tipo_servicio',
        'descripcion_servicio',
        'fecha_emision',
        'fecha_certificacion',
        'registro_volumetrico_id',
        'xml',
        'ruta_xml',
        'metadatos',
        'estado',
        'fecha_cancelacion',
        'uuid_relacionado',
    ];

    protected $casts = [
        'volumen'              => 'decimal:4',
        'precio_unitario'      => 'decimal:4',
        'subtotal'             => 'decimal:4',
        'iva'                  => 'decimal:4',
        'ieps'                 => 'decimal:4',
        'total'                => 'decimal:4',
        'fecha_emision'        => 'datetime',
        'fecha_certificacion'  => 'datetime',
        'metadatos'            => 'array',
        'fecha_cancelacion'    => 'date',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function registroVolumetrico()
    {
        return $this->belongsTo(RegistroVolumetrico::class);
    }
}