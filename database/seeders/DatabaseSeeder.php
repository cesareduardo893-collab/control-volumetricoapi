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
            UserSeeder::class,
        ]);
    }
}
