<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // Insertar valores para caracter_actua en contribuyentes
        $caracterActuaCatalogo = DB::table('catalogos')
            ->where('clave', 'CARACTER_ACTUA')
            ->first();

        if (!$caracterActuaCatalogo) {
            $caracterActuaCatalogoId = DB::table('catalogos')->insertGetId([
                'nombre' => 'caracter_actua_contribuyente',
                'clave' => 'CARACTER_ACTUA',
                'descripcion' => 'Carácter de actuación de contribuyentes',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $caracterActuaCatalogoId = $caracterActuaCatalogo->id;
        }

        $caracterActuaValores = [
            ['valor' => 'contratista', 'clave' => 'CONTRATISTA', 'descripcion' => 'Contratista'],
            ['valor' => 'asignatario', 'clave' => 'ASIGNATARIO', 'descripcion' => 'Asignatario'],
            ['valor' => 'permisionario', 'clave' => 'PERMISIONARIO', 'descripcion' => 'Permisionario'],
            ['valor' => 'usuario', 'clave' => 'USUARIO', 'descripcion' => 'Usuario'],
        ];

        foreach ($caracterActuaValores as $valor) {
            DB::table('catalogo_valores')->updateOrInsert(
                ['catalogo_id' => $caracterActuaCatalogoId, 'clave' => $valor['clave']],
                array_merge($valor, [
                    'catalogo_id' => $caracterActuaCatalogoId,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }
}