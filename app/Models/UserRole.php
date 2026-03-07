<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_role';

    protected $fillable = [
        'user_id',
        'role_id',
        'asignado_por',
        'fecha_asignacion',
        'fecha_revocacion',
        'activo',
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'fecha_revocacion' => 'datetime',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function asignadoPor()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }
}