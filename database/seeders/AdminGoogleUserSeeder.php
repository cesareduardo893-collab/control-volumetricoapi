<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminGoogleUserSeeder extends Seeder
{
    const ADMIN_EMAIL = 'controlvolumetrico69@gmail.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegurar que existan los roles básicos
        $this->call(RoleSeeder::class);

        // Verificar si el usuario admin ya existe
        $existingUser = User::where('email', self::ADMIN_EMAIL)->first();

        if ($existingUser) {
            // Actualizar usuario existente
            $existingUser->update([
                'activo' => true,
                'email_verified_at' => now(),
            ]);
            
            // Asegurar que tenga el rol de Administrador
            $adminRole = Role::where('nombre', 'Administrador')->first();
            if ($adminRole && !$existingUser->roles()->where('role_id', $adminRole->id)->exists()) {
                $existingUser->roles()->attach($adminRole->id, [
                    'asignado_por' => null,
                    'fecha_asignacion' => now(),
                    'activo' => true
                ]);
            }
            
            \Log::info('Usuario admin actualizado: ' . self::ADMIN_EMAIL);
            return;
        }

        // Crear usuario administrador
        $user = User::create([
            'identificacion' => 'ADMIN-' . strtoupper(Str::random(8)),
            'nombres' => 'Administrador',
            'apellidos' => 'Sistema',
            'email' => self::ADMIN_EMAIL,
            'telefono' => null,
            'direccion' => null,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
            'activo' => true,
            'force_password_change' => false,
            'password_expires_at' => Carbon::now()->addDays(365),
            'last_password_change' => Carbon::now(),
        ]);

        // Asignar rol de Administrador
        $adminRole = Role::where('nombre', 'Administrador')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id, [
                'asignado_por' => null,
                'fecha_asignacion' => now(),
                'activo' => true
            ]);
        }

        \Log::info('Usuario admin creado: ' . self::ADMIN_EMAIL);
    }
}
