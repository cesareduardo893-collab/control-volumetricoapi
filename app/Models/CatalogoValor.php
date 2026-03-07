<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogoValor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'catalogo_valores';

    protected $fillable = [
        'catalogo_id',
        'valor',
        'clave',
        'descripcion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'catalogo_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function catalogo()
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    public function contribuyentes()
    {
        return $this->hasMany(Contribuyente::class, 'caracter_actua_id');
    }

    public function alarmas()
    {
        return $this->hasMany(Alarma::class, 'tipo_alarma_id');
    }

    public function medidores()
    {
        return $this->hasMany(Medidor::class, 'tecnologia_id');
    }

    public function tanques()
    {
        return $this->hasMany(Tanque::class, 'tipo_tanque_id');
    }
}