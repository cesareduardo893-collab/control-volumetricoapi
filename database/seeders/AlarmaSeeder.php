<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlarmaSeeder extends Seeder
{
    public function run(): void
    {
        $alarmas = [
            [
                'numero_registro' => 'AL-2024-001',
                'fecha_hora' => '2024-01-15 10:00:00',
                'componente_tipo' => 'App\\Models\\Medidor',
                'componente_id' => 1,
                'componente_identificador' => 'MED-2020-001',
                'tipo_alarma_id' => 1,
                'gravedad' => 'ALTA',
                'descripcion' => 'La calibración del medidor vence en 30 días',
                'datos_contexto' => json_encode(['dias_restantes' => 30]),
                'diferencia_detectada' => 0.0,
                'porcentaje_diferencia' => 0.0,
                'limite_permitido' => 0.05,
                'diagnostico_automatico' => json_encode(['causa' => 'calibracion_proxima']),
                'recomendaciones' => json_encode(['agendar_calibracion']),
                'atendida' => false,
                'fecha_atencion' => null,
                'atendida_por' => null,
                'acciones_tomadas' => json_encode([]),
                'estado_atencion' => 'PENDIENTE',
                'requiere_atencion_inmediata' => false,
                'fecha_limite_atencion' => '2024-02-15 10:00:00',
                'historial_cambios_estado' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'numero_registro' => 'AL-2024-002',
                'fecha_hora' => '2024-01-15 12:00:00',
                'componente_tipo' => 'App\\Models\\Tanque',
                'componente_id' => 1,
                'componente_identificador' => 'T-01',
                'tipo_alarma_id' => 2,
                'gravedad' => 'MEDIA',
                'descripcion' => 'No se han registrado cambios en el inventario en las últimas 24 horas',
                'datos_contexto' => json_encode(['horas_sin_cambio' => 24]),
                'diferencia_detectada' => 0.0,
                'porcentaje_diferencia' => 0.0,
                'limite_permitido' => 0.0,
                'diagnostico_automatico' => json_encode(['causa' => 'inventario_sin_cambios']),
                'recomendaciones' => json_encode(['verificar_sistema']),
                'atendida' => false,
                'fecha_atencion' => null,
                'atendida_por' => null,
                'acciones_tomadas' => json_encode([]),
                'estado_atencion' => 'PENDIENTE',
                'requiere_atencion_inmediata' => false,
                'fecha_limite_atencion' => null,
                'historial_cambios_estado' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($alarmas as $alarma) {
            DB::table('alarmas')->insert($alarma);
        }
    }
}
