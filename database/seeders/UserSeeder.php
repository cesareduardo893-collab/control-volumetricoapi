<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Primero, asegurémonos de que existan los roles básicos
        $this->call(RoleSeeder::class);

        // Usuarios de ejemplo
        $users = [
            [
                'identificacion' => '1234567890',
                'nombres' => 'Administrador',
                'apellidos' => 'Sistema',
                'email' => 'admin@controlvolumetrico.com',
                'telefono' => '0987654321',
                'direccion' => 'Av. Principal 123',
                'password' => Hash::make('admin123'),
                'login_attempts' => 0,
                'last_login_at' => null,
                'password_expires_at' => Carbon::now()->addDays(90),
                'last_password_change' => Carbon::now(),
                'force_password_change' => false,
                'activo' => true,
                'roles' => ['Administrador']
            ],
            [
                'identificacion' => '0987654321',
                'nombres' => 'Usuario',
                'apellidos' => 'Regular',
                'email' => 'usuario@sistema.com',
                'telefono' => '0987654322',
                'direccion' => 'Calle Secundaria 456',
                'password' => Hash::make('password'),
                'login_attempts' => 0,
                'last_login_at' => null,
                'password_expires_at' => Carbon::now()->addDays(90),
                'last_password_change' => Carbon::now(),
                'force_password_change' => true,
                'activo' => true,
                'roles' => ['usuario']
            ],
            [
                'identificacion' => '1122334455',
                'nombres' => 'Supervisor',
                'apellidos' => 'General',
                'email' => 'supervisor@sistema.com',
                'telefono' => '0987654323',
                'direccion' => 'Av. Central 789',
                'password' => Hash::make('password'),
                'login_attempts' => 0,
                'last_login_at' => null,
                'password_expires_at' => Carbon::now()->addDays(90),
                'last_password_change' => Carbon::now(),
                'force_password_change' => false,
                'activo' => true,
                'roles' => ['supervisor']
            ],
        ];

        // Crear usuarios y asignar roles
        foreach ($users as $userData) {
            $roles = $userData['roles'];
            unset($userData['roles']);
            
            $user = User::create($userData);
            
            // Asignar roles al usuario
            $roleIds = Role::whereIn('nombre', $roles)->pluck('id');
            $user->roles()->attach($roleIds);
        }

        // Crear usuarios de prueba con Factory (si tienes factory configurado)
        if (app()->environment('local', 'development')) {
            $this->createTestUsers();
        }
    }

    /**
     * Crear usuarios adicionales para pruebas
     */
    private function createTestUsers(): void
    {
        // Usuarios adicionales con datos aleatorios usando Factory
        User::factory()
            ->count(10)
            ->create()
            ->each(function ($user) {
                // Asignar un rol aleatorio a cada usuario
                $randomRole = Role::inRandomOrder()->first();
                if ($randomRole) {
                    $user->roles()->attach($randomRole->id);
                }
            });
    }
}