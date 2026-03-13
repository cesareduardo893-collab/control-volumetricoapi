<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DispensarioSeeder extends Seeder
{
    public function run(): void
    {
        $dispensarios = [
            [
                'instalacion_id' => 1,
                'clave' => 'DISP-01',
                'descripcion' => 'Dispensario 1 - 4 mangueras',
                'modelo' => 'D-4000',
                'fabricante' => 'Dispensarios SA',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'instalacion_id' => 1,
                'clave' => 'DISP-02',
                'descripcion' => 'Dispensario 2 - 4 mangueras',
                'modelo' => 'D-4000',
                'fabricante' => 'Dispensarios SA',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'instalacion_id' => 1,
                'clave' => 'DISP-03',
                'descripcion' => 'Dispensario 3 - Diesel',
                'modelo' => 'D-2000',
                'fabricante' => 'Dispensarios SA',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($dispensarios as $dispensario) {
            DB::table('dispensarios')->insert($dispensario);
        }
    }
}
