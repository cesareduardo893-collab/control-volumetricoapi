<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'modulo',
        'reglas',
        'activo',
    ];

    protected $casts = [
        'reglas' => 'array',
        'activo' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission')
                    ->withPivot('condiciones', 'activo')
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null);
    }
}