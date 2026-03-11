<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dispensario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dispensarios';

    protected $fillable = [
        'instalacion_id',
        'clave',
        'descripcion',
        'modelo',
        'fabricante',
        'estado',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public const ESTADO_OPERATIVO = 'OPERATIVO';
    public const ESTADO_MANTENIMIENTO = 'MANTENIMIENTO';
    public const ESTADO_FUERA_SERVICIO = 'FUERA_SERVICIO';

    public function instalacion()
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function mangueras()
    {
        return $this->hasMany(Manguera::class);
    }
}