<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstalacionSeeder extends Seeder
{
    public function run(): void
    {
        $instalaciones = [
            [
                'contribuyente_id' => 1,
                'clave_instalacion' => 'INST-001',
                'nombre' => 'Estación de Servicio Principal',
                'tipo_instalacion' => 'Estación de Servicio',
                'domicilio' => 'Av. principal 500, Col. Industrial',
                'codigo_postal' => '38010',
                'municipio' => 'Celaya',
                'estado' => 'Guanajuato',
                'latitud' => 20.5328,
                'longitud' => -100.8150,
                'telefono' => '4421112233',
                'responsable' => 'Ing. Roberto Martínez',
                'fecha_operacion' => '2020-06-15',
                'estatus' => 'OPERACION',
                'configuracion_monitoreo' => json_encode(['frecuencia' => 5, 'alertas' => true]),
                'parametros_volumetricos' => json_encode(['temperatura_ref' => 15, 'presion_ref' => 1]),
                'umbrales_alarma' => json_encode(['temperatura' => 40, 'volumen' => 0.05]),
                'activo' => true,
                'observaciones' => 'Instalación principal',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($instalaciones as $instalacion) {
            DB::table('instalaciones')->insert($instalacion);
        }
    }
}
