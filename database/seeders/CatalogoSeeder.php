<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // Crear catálogos
        $catalogos = [
            ['nombre' => 'tecnologia_medidor', 'clave' => 'MED_TECH', 'descripcion' => 'Tecnologías de medición para medidores'],
            ['nombre' => 'tipo_alarma', 'clave' => 'ALARM_TYPE', 'descripcion' => 'Tipos de alarmas del sistema'],
            ['nombre' => 'tipo_tanque', 'clave' => 'TANK_TYPE', 'descripcion' => 'Tipos de tanques'],
        ];

        foreach ($catalogos as $catalogo) {
            $catalogoId = DB::table('catalogos')->insertGetId($catalogo);
            
            // Insertar valores según el catálogo
            $this->insertCatalogoValores($catalogoId, $catalogo['clave']);
        }
    }

    private function insertCatalogoValores($catalogoId, $catalogoClave)
    {
        $valores = [];

        switch ($catalogoClave) {
            case 'MED_TECH':
                $valores = [
                    ['valor' => 'ultrasonico', 'clave' => 'ULTRA', 'descripcion' => 'Ultrasonido'],
                    ['valor' => 'radar', 'clave' => 'RADAR', 'descripcion' => 'Radar'],
                    ['valor' => 'capacitivo', 'clave' => 'CAP', 'descripcion' => 'Capacitivo'],
                    ['valor' => 'magnetostrictivo', 'clave' => 'MAG', 'descripcion' => 'Magnetostrictivo'],
                    ['valor' => 'flotador', 'clave' => 'FLOAT', 'descripcion' => 'Flotador'],
                    ['valor' => 'presion_diferencial', 'clave' => 'DP', 'descripcion' => 'Presión Diferencial'],
                    ['valor' => 'desplazamiento_positivo', 'clave' => 'PD', 'descripcion' => 'Desplazamiento Positivo'],
                    ['valor' => 'turbina', 'clave' => 'TURB', 'descripcion' => 'Turbina'],
                    ['valor' => 'coriolis', 'clave' => 'COR', 'descripcion' => 'Coriolis'],
                    ['valor' => 'multifasico', 'clave' => 'MULTI', 'descripcion' => 'Multifásico'],
                    ['valor' => 'placa_orificio', 'clave' => 'ORIF', 'descripcion' => 'Placa de Orificio'],
                    ['valor' => 'otros', 'clave' => 'OTROS', 'descripcion' => 'Otros'],
                ];
                break;

            case 'ALARM_TYPE':
                $valores = [
                    ['valor' => 'calibracion_no_valida', 'clave' => 'CAL_INVALID', 'descripcion' => 'Calibración no válida'],
                    ['valor' => 'inconsistencia_volumetrica', 'clave' => 'VOL_INCONS', 'descripcion' => 'Inconsistencia volumétrica'],
                    ['valor' => 'intento_alteracion', 'clave' => 'ALTER_ATTEMPT', 'descripcion' => 'Intento de alteración'],
                    ['valor' => 'registros_incompletos', 'clave' => 'REG_INCOMP', 'descripcion' => 'Registros incompletos'],
                    ['valor' => 'problemas_comunicacion', 'clave' => 'COMM_PROB', 'descripcion' => 'Problemas de comunicación'],
                    ['valor' => 'falla_almacenamiento', 'clave' => 'STORAGE_FAIL', 'descripcion' => 'Falla de almacenamiento'],
                    ['valor' => 'falla_red', 'clave' => 'NET_FAIL', 'descripcion' => 'Falla de red'],
                    ['valor' => 'falla_energia', 'clave' => 'POWER_FAIL', 'descripcion' => 'Falla de energía'],
                    ['valor' => 'error_transmision', 'clave' => 'TRANS_ERR', 'descripcion' => 'Error de transmisión'],
                    ['valor' => 'rechazo_login', 'clave' => 'LOGIN_REJ', 'descripcion' => 'Rechazo de login'],
                    ['valor' => 'paro_emergencia', 'clave' => 'EMER_STOP', 'descripcion' => 'Paro de emergencia'],
                    ['valor' => 'reanudacion_operaciones', 'clave' => 'RES_OP', 'descripcion' => 'Reanudación de operaciones'],
                    ['valor' => 'diferencia_volumen', 'clave' => 'VOL_DIFF', 'descripcion' => 'Diferencia de volumen'],
                    ['valor' => 'inventario_cero', 'clave' => 'INV_ZERO', 'descripcion' => 'Inventario en cero'],
                    ['valor' => 'inventario_sin_cambios', 'clave' => 'INV_NO_CHG', 'descripcion' => 'Inventario sin cambios'],
                    ['valor' => 'salidas_mayor_entradas', 'clave' => 'OUT_GT_IN', 'descripcion' => 'Salidas mayor que entradas'],
                ];
                break;

            case 'TANK_TYPE':
                $valores = [
                    ['valor' => 'fijo', 'clave' => 'FIXED', 'descripcion' => 'Tanque fijo'],
                    ['valor' => 'autotanque', 'clave' => 'AUTO', 'descripcion' => 'Autotanque'],
                    ['valor' => 'carrotanque', 'clave' => 'CAR', 'descripcion' => 'Carrotanque'],
                    ['valor' => 'buquetanque', 'clave' => 'BOAT', 'descripcion' => 'Buquetanque'],
                ];
                break;
        }

        foreach ($valores as $valor) {
            DB::table('catalogo_valores')->insert(array_merge($valor, [
                'catalogo_id' => $catalogoId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}