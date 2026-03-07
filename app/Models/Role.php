<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos_detallados',
        'nivel_jerarquico',
        'es_administrador',
        'restricciones_acceso',
        'configuracion_ui',
        'activo',
    ];

    protected $casts = [
        'permisos_detallados' => 'array',
        'restricciones_acceso' => 'array',
        'configuracion_ui' => 'array',
        'es_administrador' => 'boolean',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role')
                    ->withPivot('asignado_por', 'fecha_asignacion', 'fecha_revocacion', 'activo')
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
                    ->withPivot('condiciones', 'activo')
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null);
    }
}