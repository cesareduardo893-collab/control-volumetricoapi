<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Pedimento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pedimentos';

    protected $fillable = [
        'numero_pedimento',
        'contribuyente_id',
        'producto_id',
        'punto_exportacion',
        'punto_internacion',
        'pais_destino',
        'pais_origen',
        'medio_transporte_entrada',
        'medio_transporte_salida',
        'incoterms',
        'volumen',
        'unidad_medida',
        'valor_comercial',
        'moneda',
        'fecha_pedimento',
        'fecha_arribo',
        'fecha_pago',
        'registro_volumetrico_id',
        'estado',
        'metadatos_aduana',
    ];

    protected $casts = [
        'volumen' => 'decimal:4',
        'valor_comercial' => 'decimal:4',
        'fecha_pedimento' => 'date',
        'fecha_arribo' => 'date',
        'fecha_pago' => 'date',
        'metadatos_aduana' => 'array',
    ];

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_UTILIZADO = 'UTILIZADO';
    public const ESTADO_CANCELADO = 'CANCELADO';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->numero_pedimento)) {
                $model->numero_pedimento = 'PED-' . Str::uuid();
            }
        });
    }

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function registroVolumetrico()
    {
        return $this->belongsTo(RegistroVolumetrico::class);
    }
}