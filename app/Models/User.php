<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'identificacion',
        'nombres',
        'apellidos',
        'email',
        'telefono',
        'direccion',
        'email_verified_at',
        'password',
        'login_attempts',
        'last_login_at',
        'password_expires_at',
        'last_password_change',
        'force_password_change',
        'session_token',
        'session_expires_at',
        'last_login_ip',
        'last_login_user_agent',
        'dispositivos_autorizados',
        'historial_conexiones',
        'failed_login_attempts',
        'locked_until',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'activo',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'        => 'datetime',
        'last_login_at'            => 'datetime',
        'password_expires_at'      => 'datetime',
        'last_password_change'     => 'datetime',
        'locked_until'             => 'datetime',
        'session_expires_at'       => 'datetime',
        'dispositivos_autorizados' => 'array',
        'historial_conexiones'     => 'array',
        'activo'                   => 'boolean',
        'force_password_change'    => 'boolean',
    ];

    // Relaciones
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role')
                    ->withPivot('asignado_por', 'fecha_asignacion', 'fecha_revocacion', 'activo')
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null);
    }

    public function asignacionesRoles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function registrosVolumetricos()
    {
        return $this->hasMany(RegistroVolumetrico::class, 'usuario_registro_id');
    }

    public function validacionesVolumetricas()
    {
        return $this->hasMany(RegistroVolumetrico::class, 'usuario_valida_id');
    }

    public function alarmasAtendidas()
    {
        return $this->hasMany(Alarma::class, 'atendida_por');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'usuario_id');
    }

    public function reportesSatGenerados()
    {
        return $this->hasMany(ReporteSat::class, 'usuario_genera_id');
    }
}