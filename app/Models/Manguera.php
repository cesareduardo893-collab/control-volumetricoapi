<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manguera extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mangueras';

    protected $fillable = [
        'dispensario_id',
        'clave',
        'descripcion',
        'medidor_id',
        'estado',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public const ESTADO_OPERATIVO = 'OPERATIVO';
    public const ESTADO_MANTENIMIENTO = 'MANTENIMIENTO';
    public const ESTADO_FUERA_SERVICIO = 'FUERA_SERVICIO';

    public function dispensario()
    {
        return $this->belongsTo(Dispensario::class);
    }

    public function medidor()
    {
        return $this->belongsTo(Medidor::class);
    }
}