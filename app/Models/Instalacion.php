<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instalacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'instalaciones';

    protected $fillable = [
        'contribuyente_id',
        'clave_instalacion',
        'nombre',
        'tipo_instalacion',
        'domicilio',
        'codigo_postal',
        'municipio',
        'estado',
        'latitud',
        'longitud',
        'telefono',
        'responsable',
        'fecha_operacion',
        'estatus',
        'configuracion_monitoreo',
        'parametros_volumetricos',
        'umbrales_alarma',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'fecha_operacion' => 'date',
        'configuracion_monitoreo' => 'array',
        'parametros_volumetricos' => 'array',
        'umbrales_alarma' => 'array',
        'activo' => 'boolean',
    ];

    public const ESTATUS_OPERACION = 'OPERACION';
    public const ESTATUS_SUSPENDIDA = 'SUSPENDIDA';
    public const ESTATUS_CANCELADA = 'CANCELADA';

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function tanques()
    {
        return $this->hasMany(Tanque::class);
    }

    public function medidores()
    {
        return $this->hasMany(Medidor::class);
    }

    public function dispensarios()
    {
        return $this->hasMany(Dispensario::class);
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class);
    }

    public function reportesSat()
    {
        return $this->hasMany(ReporteSat::class);
    }
    
    public function alarmas()
    {
        return $this->morphMany(Alarma::class, 'componente');
    }
}