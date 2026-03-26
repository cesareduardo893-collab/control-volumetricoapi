<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Administrador',
                'descripcion' => 'Control total del sistema',
                'permisos_detallados' => json_encode(['all']),
                'nivel_jerarquico' => 100,
                'es_administrador' => true,
                'restricciones_acceso' => json_encode([]),
                'configuracion_ui' => json_encode(['theme' => 'dark', 'menu_completo' => true])
            ],
            [
                'nombre' => 'Supervisor',
                'descripcion' => 'Puede configurar y supervisar',
                'permisos_detallados' => json_encode(['view', 'configure', 'reports']),
                'nivel_jerarquico' => 80,
                'es_administrador' => false,
                'restricciones_acceso' => json_encode(['horario' => '24/7']),
                'configuracion_ui' => json_encode(['theme' => 'light', 'menu_completo' => true])
            ],
            [
                'nombre' => 'Operador',
                'descripcion' => 'Operación diaria del sistema',
                'permisos_detallados' => json_encode(['view', 'operate']),
                'nivel_jerarquico' => 50,
                'es_administrador' => false,
                'restricciones_acceso' => json_encode(['horario' => '6:00-22:00']),
                'configuracion_ui' => json_encode(['theme' => 'light', 'menu_completo' => false])
            ],
            [
                'nombre' => 'Auditor Fiscal',
                'descripcion' => 'Solo consulta e impresión de reportes',
                'permisos_detallados' => json_encode(['view_only', 'print_reports']),
                'nivel_jerarquico' => 30,
                'es_administrador' => false,
                'restricciones_acceso' => json_encode(['ip_whitelist' => ['192.168.1.0/24']]),
                'configuracion_ui' => json_encode(['theme' => 'light', 'menu_completo' => false, 'readonly' => true])
            ]
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $role['nombre'], 'deleted_at' => null],
                array_merge($role, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
