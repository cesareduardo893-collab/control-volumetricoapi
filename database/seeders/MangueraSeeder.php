<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MangueraSeeder extends Seeder
{
    public function run(): void
    {
        $mangueras = [
            [
                'dispensario_id' => 1,
                'medidor_id' => 1,
                'clave' => 'MANG-01-1',
                'descripcion' => 'Manguera 1 - Dispensario 1',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dispensario_id' => 1,
                'medidor_id' => 1,
                'clave' => 'MANG-01-2',
                'descripcion' => 'Manguera 2 - Dispensario 1',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dispensario_id' => 2,
                'medidor_id' => 2,
                'clave' => 'MANG-02-1',
                'descripcion' => 'Manguera 1 - Dispensario 2',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dispensario_id' => 3,
                'medidor_id' => 3,
                'clave' => 'MANG-03-1',
                'descripcion' => 'Manguera 1 - Dispensario 3',
                'estado' => 'OPERATIVO',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($mangueras as $manguera) {
            DB::table('mangueras')->insert($manguera);
        }
    }
}
