<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoSeeder::class,
            UpdateCatalogoSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminGoogleUserSeeder::class,
            UserSeeder::class,
            ContribuyenteSeeder::class,
            InstalacionSeeder::class,
            ProductoSeeder::class,
            TanqueSeeder::class,
            MedidorSeeder::class,
            DispensarioSeeder::class,
            MangueraSeeder::class,
            ExistenciaSeeder::class,
            BitacoraSeeder::class,
            AlarmaSeeder::class,
        ]);
    }
}
