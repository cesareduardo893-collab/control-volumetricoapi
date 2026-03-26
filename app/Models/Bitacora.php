<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Bitacora extends Model
{
    use HasFactory;

    protected $table = 'bitacora';

    public $timestamps = true;

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

    protected $hidden = [
        'firma_digital',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'metadatos_seguridad' => 'array',
        'ip_address' => 'string',
        'user_agent' => 'string',
        'dispositivo' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TIPO_EVENTO_ADMINISTRACION = 'administracion_sistema';
    public const TIPO_EVENTO_UCC = 'eventos_ucc';
    public const TIPO_EVENTO_PROGRAMAS = 'eventos_programas';
    public const TIPO_EVENTO_COMUNICACION = 'eventos_comunicacion';
    public const TIPO_EVENTO_OPERACIONES = 'operaciones_cotidianas';
    public const TIPO_EVENTO_VERIFICACIONES = 'verificaciones_autoridad';
    public const TIPO_EVENTO_INCONSISTENCIAS = 'inconsistencias_volumetricas';
    public const TIPO_EVENTO_SEGURIDAD = 'seguridad';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->numero_registro)) {
                $model->numero_registro = self::generarNumeroRegistroSecuencial();
            }

            if (!$model->usuario_id && Auth::check()) {
                $model->usuario_id = Auth::id();
            }

            // El hash se maneja mediante trigger en MySQL
            if (DB::connection()->getDriverName() !== 'mysql') {
                $lastHash = DB::table('bitacora')->max('hash_actual');
                $model->hash_anterior = $lastHash;
                $model->hash_actual = hash('sha256', ($lastHash ?? '') . $model->descripcion . now());
            }
        });

        static::updating(function () {
            throw new \Exception('No se pueden modificar registros de bitácora');
        });

        static::deleting(function () {
            throw new \Exception('No se pueden eliminar registros de bitácora');
        });
    }

    /**
     * Generar número de registro secuencial automático
     */
    public static function generarNumeroRegistroSecuencial(): string
    {
        $ultimoNumero = self::where('numero_registro', 'like', 'BIT-%')
            ->orderBy('id', 'desc')
            ->value('numero_registro');

        if ($ultimoNumero) {
            $numero = intval(str_replace('BIT-', '', $ultimoNumero)) + 1;
        } else {
            $numero = 1;
        }

        return 'BIT-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}