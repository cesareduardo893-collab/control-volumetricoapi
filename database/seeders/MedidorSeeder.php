<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedidorSeeder extends Seeder
{
    public function run(): void
    {
        $medidores = [
            [
                'tanque_id' => 1,
                'instalacion_id' => 1,
                'numero_serie' => 'MED-2020-001',
                'clave' => 'M-01',
                'modelo' => 'VOL-1000',
                'fabricante' => 'Medidores SA',
                'elemento_tipo' => 'primario',
                'tipo_medicion' => 'dinamica',
                'tecnologia_id' => 1,
                'precision' => 0.15,
                'repetibilidad' => 0.05,
                'capacidad_maxima' => 1000.0,
                'capacidad_minima' => 10.0,
                'fecha_instalacion' => '2020-06-15',
                'ubicacion_fisica' => 'Área de dispensarios',
                'fecha_ultima_calibracion' => '2024-06-01',
                'fecha_proxima_calibracion' => '2025-06-01',
                'certificado_calibracion' => 'CERT-MED-2024-001',
                'laboratorio_calibracion' => 'Laboratorio de Calibración SA',
                'incertidumbre_calibracion' => 0.10,
                'protocolo_comunicacion' => 'modbus',
                'direccion_ip' => '192.168.1.101',
                'puerto_comunicacion' => 502,
                'parametros_conexion' => json_encode(['baudrate' => 9600, 'parity' => 'none']),
                'mecanismos_seguridad' => json_encode(['sellos' => true, 'contraseña' => true]),
                'evidencias_alteracion' => json_encode([]),
                'ultima_deteccion_alteracion' => null,
                'alerta_alteracion' => false,
                'historial_desconexiones' => json_encode([]),
                'estado' => 'OPERATIVO',
                'activo' => true,
                'observaciones' => 'Medidor para tanque de gasolina regular',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tanque_id' => 2,
                'instalacion_id' => 1,
                'numero_serie' => 'MED-2020-002',
                'clave' => 'M-02',
                'modelo' => 'VOL-1000',
                'fabricante' => 'Medidores SA',
                'elemento_tipo' => 'primario',
                'tipo_medicion' => 'dinamica',
                'tecnologia_id' => 1,
                'precision' => 0.15,
                'repetibilidad' => 0.05,
                'capacidad_maxima' => 1000.0,
                'capacidad_minima' => 10.0,
                'fecha_instalacion' => '2020-06-15',
                'ubicacion_fisica' => 'Área de dispensarios',
                'fecha_ultima_calibracion' => '2024-06-01',
                'fecha_proxima_calibracion' => '2025-06-01',
                'certificado_calibracion' => 'CERT-MED-2024-002',
                'laboratorio_calibracion' => 'Laboratorio de Calibración SA',
                'incertidumbre_calibracion' => 0.10,
                'protocolo_comunicacion' => 'modbus',
                'direccion_ip' => '192.168.1.102',
                'puerto_comunicacion' => 502,
                'parametros_conexion' => json_encode(['baudrate' => 9600, 'parity' => 'none']),
                'mecanismos_seguridad' => json_encode(['sellos' => true, 'contraseña' => true]),
                'evidencias_alteracion' => json_encode([]),
                'ultima_deteccion_alteracion' => null,
                'alerta_alteracion' => false,
                'historial_desconexiones' => json_encode([]),
                'estado' => 'OPERATIVO',
                'activo' => true,
                'observaciones' => 'Medidor para tanque de gasolina premium',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tanque_id' => 3,
                'instalacion_id' => 1,
                'numero_serie' => 'MED-2020-003',
                'clave' => 'M-03',
                'modelo' => 'VOL-2000',
                'fabricante' => 'Medidores SA',
                'elemento_tipo' => 'primario',
                'tipo_medicion' => 'dinamica',
                'tecnologia_id' => 1,
                'precision' => 0.15,
                'repetibilidad' => 0.05,
                'capacidad_maxima' => 2000.0,
                'capacidad_minima' => 20.0,
                'fecha_instalacion' => '2020-06-15',
                'ubicacion_fisica' => 'Área de dispensarios',
                'fecha_ultima_calibracion' => '2024-06-01',
                'fecha_proxima_calibracion' => '2025-06-01',
                'certificado_calibracion' => 'CERT-MED-2024-003',
                'laboratorio_calibracion' => 'Laboratorio de Calibración SA',
                'incertidumbre_calibracion' => 0.10,
                'protocolo_comunicacion' => 'modbus',
                'direccion_ip' => '192.168.1.103',
                'puerto_comunicacion' => 502,
                'parametros_conexion' => json_encode(['baudrate' => 9600, 'parity' => 'none']),
                'mecanismos_seguridad' => json_encode(['sellos' => true, 'contraseña' => true]),
                'evidencias_alteracion' => json_encode([]),
                'ultima_deteccion_alteracion' => null,
                'alerta_alteracion' => false,
                'historial_desconexiones' => json_encode([]),
                'estado' => 'OPERATIVO',
                'activo' => true,
                'observaciones' => 'Medidor para tanque de diesel',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($medidores as $medidor) {
            DB::table('medidores')->insert($medidor);
        }
    }
}
