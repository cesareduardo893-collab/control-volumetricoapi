<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dictamen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dictamenes';

    protected $fillable = [
        'folio',
        'numero_lote',
        'contribuyente_id',
        'laboratorio_rfc',
        'laboratorio_nombre',
        'laboratorio_numero_acreditacion',
        'fecha_emision',
        'fecha_toma_muestra',
        'fecha_pruebas',
        'fecha_resultados',
        'instalacion_id',
        'ubicacion_muestra',
        'producto_id',
        'volumen_muestra',
        'unidad_medida_muestra',
        'metodo_muestreo',
        'metodo_ensayo',
        'metodos_aplicados',
        'densidad_api',
        'azufre',
        'clasificacion_azufre',
        'clasificacion_api',
        'composicion_molar',
        'propiedades_fisicas',
        'propiedades_quimicas',
        'poder_calorifico',
        'poder_calorifico_superior',
        'poder_calorifico_inferior',
        'octanaje_ron',
        'octanaje_mon',
        'indice_octano',
        'contiene_bioetanol',
        'porcentaje_bioetanol',
        'contiene_biodiesel',
        'porcentaje_biodiesel',
        'contiene_bioturbosina',
        'porcentaje_bioturbosina',
        'fame',
        'porcentaje_propano',
        'porcentaje_butano',
        'propano_normalizado',
        'butano_normalizado',
        'composicion_normalizada',
        'archivo_pdf',
        'archivo_xml',
        'archivo_json',
        'archivos_adicionales',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision'             => 'date',
        'fecha_toma_muestra'        => 'date',
        'fecha_pruebas'             => 'date',
        'fecha_resultados'          => 'date',
        'volumen_muestra'           => 'decimal:2',
        'metodos_aplicados'         => 'array',
        'densidad_api'              => 'decimal:2',
        'azufre'                    => 'decimal:2',
        'composicion_molar'         => 'array',
        'propiedades_fisicas'       => 'array',
        'propiedades_quimicas'      => 'array',
        'poder_calorifico'          => 'decimal:4',
        'poder_calorifico_superior' => 'decimal:4',
        'poder_calorifico_inferior' => 'decimal:4',
        'octanaje_ron'              => 'decimal:2',
        'octanaje_mon'              => 'decimal:2',
        'indice_octano'             => 'decimal:2',
        'contiene_bioetanol'        => 'boolean',
        'porcentaje_bioetanol'      => 'decimal:2',
        'contiene_biodiesel'        => 'boolean',
        'porcentaje_biodiesel'      => 'decimal:2',
        'contiene_bioturbosina'     => 'boolean',
        'porcentaje_bioturbosina'   => 'decimal:2',
        'fame'                      => 'decimal:2',
        'porcentaje_propano'        => 'decimal:2',
        'porcentaje_butano'         => 'decimal:2',
        'propano_normalizado'       => 'decimal:2',
        'butano_normalizado'        => 'decimal:2',
        'composicion_normalizada'   => 'array',
        'archivos_adicionales'      => 'array',
    ];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class);
    }
}