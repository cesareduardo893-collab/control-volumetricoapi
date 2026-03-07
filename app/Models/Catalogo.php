<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Catalogo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'catalogos';

    protected $fillable = [
        'nombre',
        'clave',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function valores()
    {
        return $this->hasMany(CatalogoValor::class, 'catalogo_id');
    }
}