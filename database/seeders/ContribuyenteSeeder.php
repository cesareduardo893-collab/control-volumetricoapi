<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContribuyenteSeeder extends Seeder
{
    public function run(): void
    {
        $contribuyentes = [
            [
                'rfc' => 'XAXX010101000',
                'razon_social' => 'Gasolina Premium S.A. de C.V.',
                'nombre_comercial' => 'Gasolinera Premium',
                'regimen_fiscal' => 'Persona Moral',
                'domicilio_fiscal' => 'Av. Principal 100, Col. Centro',
                'codigo_postal' => '38000',
                'telefono' => '4421234567',
                'email' => 'contacto@gasolinapremium.com',
                'representante_legal' => 'Juan Pérez Sánchez',
                'representante_rfc' => 'PESJ800101ABC',
                'caracter_actua_id' => 1,
                'numero_permiso' => 'PERM-2024-001',
                'tipo_permiso' => 'Distribuidor',
                'proveedor_equipos_rfc' => 'EQU880101ABC',
                'proveedor_equipos_nombre' => 'Equipos de Medición SA de CV',
                'certificados_vigentes' => json_encode(['certificado_1.pdf', 'certificado_2.pdf']),
                'ultima_verificacion' => '2024-01-15',
                'proxima_verificacion' => '2025-01-15',
                'estatus_verificacion' => 'VIGENTE',
                'activo' => true,
                'fecha_registro' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($contribuyentes as $contribuyente) {
            DB::table('contribuyentes')->insert($contribuyente);
        }
    }
}
