<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Módulo 1: Administración del Sistema y Seguridad
            ['name' => 'Gestión de Usuarios', 'slug' => 'users.manage', 'description' => 'Registrar, modificar y dar de baja usuarios', 'modulo' => 'Administración'],
            ['name' => 'Asignar Roles', 'slug' => 'roles.assign', 'description' => 'Asignar perfiles a usuarios', 'modulo' => 'Administración'],
            ['name' => 'Configurar Seguridad', 'slug' => 'security.configure', 'description' => 'Establecer reglas de contraseñas y sesiones', 'modulo' => 'Administración'],
            ['name' => 'Ver Usuarios', 'slug' => 'users.view', 'description' => 'Visualizar lista de usuarios', 'modulo' => 'Administración'],

            // Módulo 2: Configuración de Infraestructura
            ['name' => 'Gestionar Instalaciones', 'slug' => 'instalaciones.manage', 'description' => 'Alta, baja y modificación de instalaciones', 'modulo' => 'Infraestructura'],
            ['name' => 'Gestionar Tanques', 'slug' => 'tanques.manage', 'description' => 'Alta, baja y modificación de tanques', 'modulo' => 'Infraestructura'],
            ['name' => 'Gestionar Medidores', 'slug' => 'medidores.manage', 'description' => 'Alta, baja y modificación de medidores', 'modulo' => 'Infraestructura'],
            ['name' => 'Gestionar Dispensarios', 'slug' => 'dispensarios.manage', 'description' => 'Alta, baja y modificación de dispensarios', 'modulo' => 'Infraestructura'],
            ['name' => 'Gestionar Mangueras', 'slug' => 'mangueras.manage', 'description' => 'Alta, baja y modificación de mangueras', 'modulo' => 'Infraestructura'],
            ['name' => 'Ver Infraestructura', 'slug' => 'infraestructura.view', 'description' => 'Visualizar información de infraestructura', 'modulo' => 'Infraestructura'],

            // Módulo 3: Operaciones y Volúmenes
            ['name' => 'Ver Despliegues', 'slug' => 'despliegues.view', 'description' => 'Visualización de despliegues gráficos', 'modulo' => 'Operaciones'],
            ['name' => 'Ver Volúmenes', 'slug' => 'volumenes.view', 'description' => 'Visualización de datos de volumen', 'modulo' => 'Operaciones'],
            ['name' => 'Registrar en Bitácora', 'slug' => 'bitacora.register', 'description' => 'Registro manual de eventos', 'modulo' => 'Operaciones'],
            ['name' => 'Gestionar Alarmas', 'slug' => 'alarmas.manage', 'description' => 'Gestión de alarmas del sistema', 'modulo' => 'Operaciones'],
            ['name' => 'Ver Alarmas', 'slug' => 'alarmas.view', 'description' => 'Visualización de alarmas', 'modulo' => 'Operaciones'],

            // Módulo 4: Reportes
            ['name' => 'Generar Reportes', 'slug' => 'reportes.generate', 'description' => 'Generar reportes diarios y mensuales', 'modulo' => 'Reportes'],
            ['name' => 'Ver Reportes', 'slug' => 'reportes.view', 'description' => 'Consultar reportes existentes', 'modulo' => 'Reportes'],
            ['name' => 'Imprimir Reportes', 'slug' => 'reportes.print', 'description' => 'Imprimir reportes', 'modulo' => 'Reportes'],
            ['name' => 'Firmar Reportes', 'slug' => 'reportes.sign', 'description' => 'Firma electrónica de reportes SAT', 'modulo' => 'Reportes'],

            // Módulo 5: Bitácoras
            ['name' => 'Ver Bitácora', 'slug' => 'bitacora.view', 'description' => 'Visualización de bitácora de eventos', 'modulo' => 'Bitácora'],
            ['name' => 'Exportar Bitácora', 'slug' => 'bitacora.export', 'description' => 'Exportar registros de bitácora', 'modulo' => 'Bitácora'],

            // Módulo 6: Contribuyentes y Productos
            ['name' => 'Gestionar Contribuyentes', 'slug' => 'contribuyentes.manage', 'description' => 'Alta, baja y modificación de contribuyentes', 'modulo' => 'Catálogos'],
            ['name' => 'Gestionar Productos', 'slug' => 'productos.manage', 'description' => 'Alta, baja y modificación de productos', 'modulo' => 'Catálogos'],
            ['name' => 'Ver Catálogos', 'slug' => 'catalogos.view', 'description' => 'Visualizar catálogos', 'modulo' => 'Catálogos'],

            // Módulo 7: Existencias y Registros
            ['name' => 'Ver Existencias', 'slug' => 'existencias.view', 'description' => 'Visualizar existencias', 'modulo' => 'Existencias'],
            ['name' => 'Validar Existencias', 'slug' => 'existencias.validate', 'description' => 'Validar registros de existencias', 'modulo' => 'Existencias'],
            ['name' => 'Ver Registros Volumétricos', 'slug' => 'registros.view', 'description' => 'Visualizar registros volumétricos', 'modulo' => 'Registros'],
            ['name' => 'Gestionar Registros', 'slug' => 'registros.manage', 'description' => 'Crear y modificar registros volumétricos', 'modulo' => 'Registros'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug'], 'deleted_at' => null],
                array_merge($permission, [
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Asignar permisos a roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        // Obtener IDs de roles
        $adminRole = DB::table('roles')->where('nombre', 'Administrador')->first();
        $supervisorRole = DB::table('roles')->where('nombre', 'Supervisor')->first();
        $operadorRole = DB::table('roles')->where('nombre', 'Operador')->first();
        $auditorRole = DB::table('roles')->where('nombre', 'Auditor Fiscal')->first();

        // Obtener todos los permisos
        $allPermissions = DB::table('permissions')->where('deleted_at', null)->pluck('id', 'slug')->toArray();

        // Limpiar asignaciones existentes
        DB::table('role_permission')->truncate();

        // Administrador: Todos los permisos
        if ($adminRole) {
            foreach ($allPermissions as $permId) {
                DB::table('role_permission')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permId,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Supervisor: Todo excepto gestión de usuarios y seguridad
        $supervisorPerms = [
            'instalaciones.manage', 'tanques.manage', 'medidores.manage',
            'dispensarios.manage', 'mangueras.manage', 'infraestructura.view',
            'despliegues.view', 'volumenes.view', 'bitacora.register',
            'alarmas.manage', 'alarmas.view',
            'reportes.generate', 'reportes.view', 'reportes.print', 'reportes.sign',
            'bitacora.view', 'bitacora.export',
            'contribuyentes.manage', 'productos.manage', 'catalogos.view',
            'existencias.view', 'existencias.validate',
            'registros.view', 'registros.manage'
        ];
        if ($supervisorRole) {
            foreach ($supervisorPerms as $slug) {
                if (isset($allPermissions[$slug])) {
                    DB::table('role_permission')->insert([
                        'role_id' => $supervisorRole->id,
                        'permission_id' => $allPermissions[$slug],
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // Operador: Solo lectura y registro en bitácora
        $operadorPerms = [
            'infraestructura.view', 'despliegues.view', 'volumenes.view',
            'bitacora.register', 'alarmas.view',
            'reportes.view',
            'bitacora.view',
            'catalogos.view',
            'existencias.view',
            'registros.view'
        ];
        if ($operadorRole) {
            foreach ($operadorPerms as $slug) {
                if (isset($allPermissions[$slug])) {
                    DB::table('role_permission')->insert([
                        'role_id' => $operadorRole->id,
                        'permission_id' => $allPermissions[$slug],
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // Auditor Fiscal: Solo consulta y reportes
        $auditorPerms = [
            'infraestructura.view', 'despliegues.view', 'volumenes.view',
            'alarmas.view',
            'reportes.generate', 'reportes.view', 'reportes.print', 'reportes.sign',
            'bitacora.view',
            'catalogos.view',
            'existencias.view',
            'registros.view'
        ];
        if ($auditorRole) {
            foreach ($auditorPerms as $slug) {
                if (isset($allPermissions[$slug])) {
                    DB::table('role_permission')->insert([
                        'role_id' => $auditorRole->id,
                        'permission_id' => $allPermissions[$slug],
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}
