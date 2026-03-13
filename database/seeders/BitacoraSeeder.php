<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BitacoraSeeder extends Seeder
{
    public function run(): void
    {
        $bitacoras = [
            [
                'numero_registro' => 'BIT-2024-001',
                'usuario_id' => 1,
                'tipo_evento' => 'administracion_sistema',
                'subtipo_evento' => 'login',
                'modulo' => 'auth',
                'tabla' => 'users',
                'registro_id' => 1,
                'datos_anteriores' => json_encode([]),
                'datos_nuevos' => json_encode(['login' => '2024-01-15 08:00:00']),
                'descripcion' => 'Inicio de sesión exitoso',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'dispositivo' => 'Desktop',
                'metadatos_seguridad' => json_encode([]),
                'observaciones' => 'Usuario inició sesión correctamente',
                'hash_anterior' => null,
                'hash_actual' => hash('sha256', 'BIT-2024-001'),
                'firma_digital' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'numero_registro' => 'BIT-2024-002',
                'usuario_id' => 1,
                'tipo_evento' => 'operaciones_cotidianas',
                'subtipo_evento' => 'crear_registro',
                'modulo' => 'volumetrico',
                'tabla' => 'registros_volumetricos',
                'registro_id' => 1,
                'datos_anteriores' => json_encode([]),
                'datos_nuevos' => json_encode(['volumen' => 100.50]),
                'descripcion' => 'Creación de registro volumétrico',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'dispositivo' => 'Desktop',
                'metadatos_seguridad' => json_encode([]),
                'observaciones' => 'Se creó un nuevo registro volumétrico',
                'hash_anterior' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'hash_actual' => hash('sha256', 'BIT-2024-002'),
                'firma_digital' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($bitacoras as $bitacora) {
            DB::table('bitacora')->insert($bitacora);
        }
    }
}
