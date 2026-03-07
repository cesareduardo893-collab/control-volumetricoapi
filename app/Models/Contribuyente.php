<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contribuyente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contribuyentes';

    protected $fillable = [
        'rfc',
        'razon_social',
        'nombre_comercial',
        'regimen_fiscal',
        'domicilio_fiscal',
        'codigo_postal',
        'telefono',
        'email',
        'representante_legal',
        'representante_rfc',
        'caracter_actua_id',
        'numero_permiso',
        'tipo_permiso',
        'proveedor_equipos_rfc',
        'proveedor_equipos_nombre',
        'certificados_vigentes',
        'ultima_verificacion',
        'proxima_verificacion',
        'estatus_verificacion',
        'activo',
        'fecha_registro',
    ];

    protected $casts = [
        'certificados_vigentes' => 'array',
        'ultima_verificacion'   => 'date',
        'proxima_verificacion'  => 'date',
        'activo'                 => 'boolean',
        'fecha_registro'         => 'date',
    ];

    public function instalaciones()
    {
        return $this->hasMany(Instalacion::class);
    }

    public function dictamenes()
    {
        return $this->hasMany(Dictamen::class);
    }

    public function pedimentos()
    {
        return $this->hasMany(Pedimento::class);
    }

    public function certificadosVerificacion()
    {
        return $this->hasMany(CertificadoVerificacion::class);
    }

    public function caracterActua()
    {
        return $this->belongsTo(CatalogoValor::class, 'caracter_actua_id');
    }
}
