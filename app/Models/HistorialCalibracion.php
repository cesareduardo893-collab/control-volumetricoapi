<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialCalibracion extends Model
{
    use HasFactory;

    protected $table = 'historial_calibraciones';

    protected $fillable = [
        'tanque_id',
        'fecha_calibracion',
        'fecha_proxima_calibracion',
        'certificado_calibracion',
        'entidad_calibracion',
        'incertidumbre_medicion',
        'tabla_aforo',
        'curvas_calibracion',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_calibracion' => 'date',
        'fecha_proxima_calibracion' => 'date',
        'incertidumbre_medicion' => 'decimal:3',
        'tabla_aforo' => 'array',
        'curvas_calibracion' => 'array',
    ];

    public function tanque()
    {
        return $this->belongsTo(Tanque::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}