<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialConexion extends Model
{
    use HasFactory;

    protected $table = 'historial_conexiones';

    protected $fillable = [
        'user_id',
        'fecha_hora',
        'ip_address',
        'user_agent',
        'dispositivo',
        'exitosa',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'exitosa' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}