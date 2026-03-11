<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'medidores';

    protected $fillable = [
        'tanque_id',
        'instalacion_id',
        'numero_serie',
        'clave',
        'modelo',
        'fabricante',
        'elemento_tipo',
        'tipo_medicion',
        'tecnologia_id',
        'precision',
        'repetibilidad',
        'capacidad_maxima',
        'capacidad_minima',
        'fecha_instalacion',
        'ubicacion_fisica',
        'fecha_ultima_calibracion',
        'fecha_proxima_calibracion',
        'certificado_calibracion',
        'laboratorio_calibracion',
        'incertidumbre_calibracion',
        'protocolo_comunicacion',
        'direccion_ip',
        'puerto_comunicacion',
        'parametros_conexion',
        'mecanismos_seguridad',
        'evidencias_alteracion',
        'ultima_deteccion_alteracion',
        'alerta_alteracion',
        'historial_desconexiones',
        'estado',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'precision' => 'decimal:3',
        'repetibilidad' => 'decimal:3',
        'capacidad_maxima' => 'decimal:4',
        'capacidad_minima' => 'decimal:4',
        'fecha_instalacion' => 'date',
        'fecha_ultima_calibracion' => 'date',
        'fecha_proxima_calibracion' => 'date',
        'incertidumbre_calibracion' => 'decimal:3',
        'parametros_conexion' => 'array',
        'mecanismos_seguridad' => 'array',
        'evidencias_alteracion' => 'array',
        'ultima_deteccion_alteracion' => 'datetime',
        'alerta_alteracion' => 'boolean',
        'historial_desconexiones' => 'array',
        'activo' => 'boolean',
    ];

    public const ELEMENTO_TIPO_PRIMARIO = 'primario';
    public const ELEMENTO_TIPO_SECUNDARIO = 'secundario';
    public const ELEMENTO_TIPO_TERCIARIO = 'terciario';

    public const TIPO_MEDICION_ESTATICA = 'estatica';
    public const TIPO_MEDICION_DINAMICA = 'dinamica';

    public const ESTADO_OPERATIVO = 'OPERATIVO';
    public const ESTADO_CALIBRACION = 'CALIBRACION';
    public const ESTADO_MANTENIMIENTO = 'MANTENIMIENTO';
    public const ESTADO_FUERA_SERVICIO = 'FUERA_SERVICIO';
    public const ESTADO_FALLA_COMUNICACION = 'FALLA_COMUNICACION';

    public function tanque()
    {
        return $this->belongsTo(Tanque::class);
    }

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function mangueras()
    {
        return $this->hasMany(Manguera::class);
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class);
    }

    public function tecnologia()
    {
        return $this->belongsTo(CatalogoValor::class, 'tecnologia_id');
    }

    public function historialCalibracionesMedidor()
    {
        return $this->hasMany(HistorialCalibracionMedidor::class, 'medidor_id');
    }
}