<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            [
                'clave_sat' => '15101501',
                'codigo' => 'GAS-REG',
                'clave_identificacion' => 'GASOLINA_REGULAR',
                'nombre' => 'Gasolina Regular Magna',
                'descripcion' => 'Gasolina regular con 87 octanos',
                'unidad_medida' => 'Litro',
                'tipo_hidrocarburo' => 'gasolina',
                'densidad_api' => 30.0,
                'contenido_azufre' => 0.015,
                'clasificacion_azufre' => 'ULS',
                'clasificacion_api' => 'premium',
                'poder_calorifico' => 11500.0,
                'composicion_tipica' => json_encode(['C8' => 0.85, 'C9' => 0.15]),
                'especificaciones_tecnicas' => json_encode(['octanaje_min' => 87]),
                'octanaje_ron' => 87.0,
                'octanaje_mon' => 82.0,
                'indice_octano' => 84.5,
                'contiene_bioetanol' => true,
                'porcentaje_bioetanol' => 10.0,
                'contiene_biodiesel' => false,
                'porcentaje_biodiesel' => 0.0,
                'contiene_bioturbosina' => false,
                'porcentaje_bioturbosina' => 0.0,
                'fame' => 0.0,
                'porcentaje_propano' => 0.0,
                'porcentaje_butano' => 0.0,
                'propano_normalizado' => 0.0,
                'butano_normalizado' => 0.0,
                'indice_wobbe' => 0.0,
                'clasificacion_gas' => null,
                'color_identificacion' => '#FF0000',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'clave_sat' => '15101502',
                'codigo' => 'GAS-PRE',
                'clave_identificacion' => 'GASOLINA_PREMIUM',
                'nombre' => 'Gasolina Premium',
                'descripcion' => 'Gasolina premium con 93 octanos',
                'unidad_medida' => 'Litro',
                'tipo_hidrocarburo' => 'gasolina',
                'densidad_api' => 28.0,
                'contenido_azufre' => 0.010,
                'clasificacion_azufre' => 'ULS',
                'clasificacion_api' => 'super',
                'poder_calorifico' => 11600.0,
                'composicion_tipica' => json_encode(['C8' => 0.90, 'C9' => 0.10]),
                'especificaciones_tecnicas' => json_encode(['octanaje_min' => 93]),
                'octanaje_ron' => 93.0,
                'octanaje_mon' => 88.0,
                'indice_octano' => 90.5,
                'contiene_bioetanol' => true,
                'porcentaje_bioetanol' => 10.0,
                'contiene_biodiesel' => false,
                'porcentaje_biodiesel' => 0.0,
                'contiene_bioturbosina' => false,
                'porcentaje_bioturbosina' => 0.0,
                'fame' => 0.0,
                'porcentaje_propano' => 0.0,
                'porcentaje_butano' => 0.0,
                'propano_normalizado' => 0.0,
                'butano_normalizado' => 0.0,
                'indice_wobbe' => 0.0,
                'clasificacion_gas' => null,
                'color_identificacion' => '#FF8800',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'clave_sat' => '15101601',
                'codigo' => 'DSL',
                'clave_identificacion' => 'DIESEL',
                'nombre' => 'Diiesel',
                'descripcion' => 'Diiesel de ultra bajo azufre',
                'unidad_medida' => 'Litro',
                'tipo_hidrocarburo' => 'diesel',
                'densidad_api' => 35.0,
                'contenido_azufre' => 0.005,
                'clasificacion_azufre' => 'ULS',
                'clasificacion_api' => 'diesel',
                'poder_calorifico' => 12000.0,
                'composicion_tipica' => json_encode(['C12' => 0.70, 'C14' => 0.30]),
                'especificaciones_tecnicas' => json_encode(['cetano_min' => 48]),
                'octanaje_ron' => 0.0,
                'octanaje_mon' => 0.0,
                'indice_octano' => 0.0,
                'contiene_bioetanol' => false,
                'porcentaje_bioetanol' => 0.0,
                'contiene_biodiesel' => true,
                'porcentaje_biodiesel' => 5.0,
                'contiene_bioturbosina' => false,
                'porcentaje_bioturbosina' => 0.0,
                'fame' => 5.0,
                'porcentaje_propano' => 0.0,
                'porcentaje_butano' => 0.0,
                'propano_normalizado' => 0.0,
                'butano_normalizado' => 0.0,
                'indice_wobbe' => 0.0,
                'clasificacion_gas' => null,
                'color_identificacion' => '#008800',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($productos as $producto) {
            DB::table('productos')->insert($producto);
        }
    }
}
