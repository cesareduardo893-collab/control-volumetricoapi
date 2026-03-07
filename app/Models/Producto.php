<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'clave_sat',
        'codigo',
        'clave_identificacion',
        'nombre',
        'descripcion',
        'unidad_medida',
        'tipo_hidrocarburo',
        'densidad_api',
        'contenido_azufre',
        'clasificacion_azufre',
        'clasificacion_api',
        'poder_calorifico',
        'composicion_tipica',
        'especificaciones_tecnicas',
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
        'indice_wobbe',
        'clasificacion_gas',
        'color_identificacion',
        'activo',
    ];

    protected $casts = [
        'densidad_api'               => 'decimal:2',
        'contenido_azufre'           => 'decimal:2',
        'poder_calorifico'            => 'decimal:4',
        'composicion_tipica'          => 'array',
        'especificaciones_tecnicas'   => 'array',
        'octanaje_ron'                => 'decimal:2',
        'octanaje_mon'                => 'decimal:2',
        'indice_octano'               => 'decimal:2',
        'contiene_bioetanol'          => 'boolean',
        'porcentaje_bioetanol'        => 'decimal:2',
        'contiene_biodiesel'          => 'boolean',
        'porcentaje_biodiesel'        => 'decimal:2',
        'contiene_bioturbosina'       => 'boolean',
        'porcentaje_bioturbosina'     => 'decimal:2',
        'fame'                        => 'decimal:2',
        'porcentaje_propano'          => 'decimal:2',
        'porcentaje_butano'           => 'decimal:2',
        'propano_normalizado'         => 'decimal:2',
        'butano_normalizado'          => 'decimal:2',
        'indice_wobbe'                => 'decimal:4',
        'activo'                      => 'boolean',
    ];

    public function tanques()
    {
        return $this->hasMany(Tanque::class);
    }

    public function existencias()
    {
        return $this->hasMany(Existencia::class);
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class);
    }

    public function dictamenes()
    {
        return $this->hasMany(Dictamen::class);
    }

    public function cfdis()
    {
        return $this->hasMany(Cfdi::class);
    }

    public function pedimentos()
    {
        return $this->hasMany(Pedimento::class);
    }
}