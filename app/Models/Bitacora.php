<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Bitacora extends Model
{
    use HasFactory;

    protected $table = 'bitacora';

    public $timestamps = true;

    const UPDATED_AT = null; // No se permite actualización

    protected $fillable = [
        'numero_registro',
        'usuario_id',
        'tipo_evento',
        'subtipo_evento',
        'modulo',
        'tabla',
        'registro_id',
        'datos_anteriores',
        'datos_nuevos',
        'descripcion',
        'ip_address',
        'user_agent',
        'dispositivo',
        'metadatos_seguridad',
        'observaciones',
        'hash_anterior',
        'hash_actual',
        'firma_digital',
    ];

    protected $casts = [
        'datos_anteriores'      => 'array',
        'datos_nuevos'          => 'array',
        'metadatos_seguridad'   => 'array',
        'created_at'            => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generar hash secuencial
            $lastHash = DB::table('bitacora_hash_sequence')
                ->where('id', 1)
                ->value('last_hash');

            $model->hash_anterior = $lastHash;
            $model->hash_actual = hash('sha256', ($lastHash ?? '') . $model->descripcion . now());
            
            // Actualizar el último hash
            DB::table('bitacora_hash_sequence')
                ->where('id', 1)
                ->update(['last_hash' => $model->hash_actual]);

            // Asignar usuario actual si no está definido
            if (!$model->usuario_id && Auth::check()) {
                $model->usuario_id = Auth::id();
            }
        });

        static::updating(function () {
            throw new \Exception('No se pueden modificar registros de bitácora');
        });

        static::deleting(function () {
            throw new \Exception('No se pueden eliminar registros de bitácora');
        });
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
