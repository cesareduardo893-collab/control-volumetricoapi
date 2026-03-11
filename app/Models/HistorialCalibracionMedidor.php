<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialCalibracionMedidor extends Model
{
    use HasFactory;

    protected $table = 'historial_calibraciones_medidores';

    protected $fillable = [
        'medidor_id',
        'fecha_calibracion',
        'fecha_proxima_calibracion',
        'certificado_calibracion',
        'laboratorio_calibracion',
        'incertidumbre_calibracion',
        'precision',
        'repetibilidad',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_calibracion' => 'date',
        'fecha_proxima_calibracion' => 'date',
        'incertidumbre_calibracion' => 'decimal:3',
        'precision' => 'decimal:3',
        'repetibilidad' => 'decimal:3',
    ];

    public function medidor()
    {
        return $this->belongsTo(Medidor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}