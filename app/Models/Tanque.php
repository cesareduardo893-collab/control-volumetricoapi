<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tanque extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tanques';

    protected $fillable = [
        'instalacion_id',
        'producto_id',
        'numero_serie',
        'identificador',
        'tipo_tanque_id',
        'placas',
        'numero_economico',
        'modelo',
        'fabricante',
        'material',
        'capacidad_total',
        'capacidad_util',
        'capacidad_operativa',
        'capacidad_minima',
        'capacidad_gas_talon',
        'fecha_fabricacion',
        'fecha_instalacion',
        'fecha_ultima_calibracion',
        'fecha_proxima_calibracion',
        'certificado_calibracion',
        'entidad_calibracion',
        'incertidumbre_medicion',
        'historial_calibraciones',
        'temperatura_referencia',
        'presion_referencia',
        'tipo_medicion',
        'estado',
        'tabla_aforo',
        'curvas_calibracion',
        'evidencias_alteracion',
        'ultima_deteccion_alteracion',
        'alerta_alteracion',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'capacidad_total'               => 'decimal:4',
        'capacidad_util'                 => 'decimal:4',
        'capacidad_operativa'            => 'decimal:4',
        'capacidad_minima'               => 'decimal:4',
        'capacidad_gas_talon'            => 'decimal:4',
        'fecha_fabricacion'              => 'date',
        'fecha_instalacion'              => 'date',
        'fecha_ultima_calibracion'       => 'date',
        'fecha_proxima_calibracion'      => 'date',
        'incertidumbre_medicion'         => 'decimal:3',
        'historial_calibraciones'        => 'array',
        'temperatura_referencia'         => 'decimal:2',
        'presion_referencia'             => 'decimal:3',
        'tabla_aforo'                    => 'array',
        'curvas_calibracion'             => 'array',
        'evidencias_alteracion'          => 'array',
        'ultima_deteccion_alteracion'    => 'datetime',
        'alerta_alteracion'              => 'boolean',
        'activo'                         => 'boolean',
    ];

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function medidores()
    {
        return $this->hasMany(Medidor::class);
    }

    public function existencias()
    {
        return $this->hasMany(Existencia::class);
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class);
    }

    public function tipoTanque()
    {
        return $this->belongsTo(CatalogoValor::class, 'tipo_tanque_id');
    }
}
