<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExistenciaSeeder extends Seeder
{
    public function run(): void
    {
        $existencias = [
            [
                'numero_registro' => 'EX-2024-001',
                'tanque_id' => 1,
                'producto_id' => 1,
                'fecha' => '2024-01-15',
                'hora' => '08:00:00',
                'volumen_medido' => 5000.0,
                'temperatura' => 25.0,
                'presion' => 1.0,
                'densidad' => 0.74,
                'volumen_corregido' => 4900.0,
                'factor_correccion_temperatura' => 0.98,
                'factor_correccion_presion' => 1.0,
                'volumen_disponible' => 4900.0,
                'volumen_agua' => 0.0,
                'volumen_sedimentos' => 0.0,
                'volumen_inicial_dia' => 5000.0,
                'volumen_calculado' => 4900.0,
                'diferencia_volumen' => 0.0,
                'porcentaje_diferencia' => 0.0,
                'detalle_calculo' => json_encode(['nota' => 'Registro inicial']),
                'tipo_registro' => 'inicial',
                'tipo_movimiento' => 'INICIAL',
                'documento_referencia' => null,
                'rfc_contraparte' => null,
                'observaciones' => 'Existencia inicial del día',
                'usuario_registro_id' => 1,
                'usuario_valida_id' => 1,
                'fecha_validacion' => '2024-01-15 08:30:00',
                'estado' => 'VALIDADO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'numero_registro' => 'EX-2024-002',
                'tanque_id' => 2,
                'producto_id' => 2,
                'fecha' => '2024-01-15',
                'hora' => '08:00:00',
                'volumen_medido' => 4500.0,
                'temperatura' => 25.5,
                'presion' => 1.0,
                'densidad' => 0.76,
                'volumen_corregido' => 4410.0,
                'factor_correccion_temperatura' => 0.98,
                'factor_correccion_presion' => 1.0,
                'volumen_disponible' => 4410.0,
                'volumen_agua' => 0.0,
                'volumen_sedimentos' => 0.0,
                'volumen_inicial_dia' => 4500.0,
                'volumen_calculado' => 4410.0,
                'diferencia_volumen' => 0.0,
                'porcentaje_diferencia' => 0.0,
                'detalle_calculo' => json_encode(['nota' => 'Registro inicial']),
                'tipo_registro' => 'inicial',
                'tipo_movimiento' => 'INICIAL',
                'documento_referencia' => null,
                'rfc_contraparte' => null,
                'observaciones' => 'Existencia inicial del día',
                'usuario_registro_id' => 1,
                'usuario_valida_id' => 1,
                'fecha_validacion' => '2024-01-15 08:30:00',
                'estado' => 'VALIDADO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'numero_registro' => 'EX-2024-003',
                'tanque_id' => 3,
                'producto_id' => 3,
                'fecha' => '2024-01-15',
                'hora' => '08:00:00',
                'volumen_medido' => 12000.0,
                'temperatura' => 24.0,
                'presion' => 1.0,
                'densidad' => 0.84,
                'volumen_corregido' => 11880.0,
                'factor_correccion_temperatura' => 0.99,
                'factor_correccion_presion' => 1.0,
                'volumen_disponible' => 11880.0,
                'volumen_agua' => 0.0,
                'volumen_sedimentos' => 0.0,
                'volumen_inicial_dia' => 12000.0,
                'volumen_calculado' => 11880.0,
                'diferencia_volumen' => 0.0,
                'porcentaje_diferencia' => 0.0,
                'detalle_calculo' => json_encode(['nota' => 'Registro inicial']),
                'tipo_registro' => 'inicial',
                'tipo_movimiento' => 'INICIAL',
                'documento_referencia' => null,
                'rfc_contraparte' => null,
                'observaciones' => 'Existencia inicial del día',
                'usuario_registro_id' => 1,
                'usuario_valida_id' => 1,
                'fecha_validacion' => '2024-01-15 08:30:00',
                'estado' => 'VALIDADO',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($existencias as $existencia) {
            DB::table('existencias')->insert($existencia);
        }
    }
}
