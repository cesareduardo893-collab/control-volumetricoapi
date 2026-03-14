<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ExportarImportarController extends Controller
{
    /**
     * Export data for a module in CSV (Excel-compatible) or simple PDF format
     */
    public function exportar($modulo, Request $request)
    {
        // Validate modulo
        $allowedModulos = [
            'alarmas', 'bitacora', 'certificados-verificacion', 'cfdi', 'contribuyentes',
            'dictamenes', 'dispensarios', 'existencias', 'instalaciones', 'mangueras',
            'medidores', 'pedimentos', 'permisos', 'productos', 'registros-volumetricos',
            'reportes-sat', 'roles', 'tanques', 'users'
        ];

        if (!in_array($modulo, $allowedModulos)) {
            return response()->json(['error' => 'Módulo no válido'], 400);
        }

        // Get export type (csv or pdf)
        $tipo = $request->query('tipo', 'csv');
        if (!in_array($tipo, ['csv', 'pdf'])) {
            return response()->json(['error' => 'Tipo de exportación no válido'], 400);
        }

        // Get data based on module
        $data = $this->getDataForModule($modulo, $request);

        // Generate file based on type
        if ($tipo === 'csv') {
            return $this->exportCsv($data, $modulo);
        } else {
            return $this->exportSimplePdf($data, $modulo);
        }
    }

    /**
     * Import data from CSV file for a module
     */
    public function importar($modulo, Request $request)
    {
        // Validate modulo
        $allowedModulos = [
            'alarmas', 'bitacora', 'certificados-verificacion', 'cfdi', 'contribuyentes',
            'dictamenes', 'dispensarios', 'existencias', 'instalaciones', 'mangueras',
            'medidores', 'pedimentos', 'permisos', 'productos', 'registros-volumetricos',
            'reportes-sat', 'roles', 'tanques', 'users'
        ];

        if (!in_array($modulo, $allowedModulos)) {
            return response()->json(['error' => 'Módulo no válido'], 400);
        }

        // Validate file
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        // Process import based on module
        $result = $this->processImport($modulo, $request->file('file'));

        return response()->json($result);
    }

    /**
     * Get data for a specific module
     */
    private function getDataForModule($modulo, Request $request)
    {
        // Map module names to table names
        $tableMap = [
            'alarmas' => 'alarmas',
            'bitacora' => 'bitacora',
            'certificados-verificacion' => 'certificados_verificacion',
            'cfdi' => 'cfdi',
            'contribuyentes' => 'contribuyentes',
            'dictamenes' => 'dictamenes',
            'dispensarios' => 'dispensarios',
            'existencias' => 'existencias',
            'instalaciones' => 'instalaciones',
            'mangueras' => 'mangueras',
            'medidores' => 'medidores',
            'pedimentos' => 'pedimentos',
            'permisos' => 'permissions',
            'productos' => 'productos',
            'registros-volumetricos' => 'registros_volumetricos',
            'reportes-sat' => 'reportes_sat',
            'roles' => 'roles',
            'tanques' => 'tanques',
            'users' => 'users'
        ];

        $table = $tableMap[$modulo] ?? $modulo;

        // Get all data from the table (you might want to add filters based on request)
        return DB::table($table)->get();
    }

    /**
     * Export data to CSV (Excel-compatible)
     */
    private function exportCsv($data, $modulo)
    {
        // Create CSV content
        $output = fopen('php://output', 'w');
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $modulo . '_' . now()->format('Y-m-d_H-i-s') . '.csv');
        
        // Add BOM for UTF-8 in Excel
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        // Add headers
        if ($data->count() > 0) {
            $headers = array_keys((array) $data[0]);
            fputcsv($output, $headers);
            
            // Add rows
            foreach ($data as $row) {
                fputcsv($output, array_values((array) $row));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export data to simple PDF
     */
    private function exportSimplePdf($data, $modulo)
    {
        // Create a simple HTML table
        $html = '<h1>Exportar ' . ucfirst(str_replace('-', ' ', $modulo)) . '</h1>';
        $html .= '<p>Fecha de exportación: ' . now()->format('d/m/Y H:i:s') . '</p>';
        
        if ($data->count() > 0) {
            $html .= '<table border="1" cellpadding="5" cellspacing="0">';
            
            // Add headers
            $html .= '<tr style="background-color: #f2f2f2;">';
            $headers = array_keys((array) $data[0]);
            foreach ($headers as $header) {
                $html .= '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
            }
            $html .= '</tr>';
            
            // Add rows
            foreach ($data as $row) {
                $html .= '<tr>';
                foreach ((array) $row as $cell) {
                    $html .= '<td>' . htmlspecialchars($cell ?? '') . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        } else {
            $html .= '<p>No hay datos para exportar.</p>';
        }
        
        // Generate PDF using dompdf (if available) or return HTML
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename=' . $modulo . '_' . now()->format('Y-m-d_H-i-s') . '.pdf');
        } else {
            // Fallback to HTML if dompdf is not available
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename=' . $modulo . '_' . now()->format('Y-m-d_H-i-s') . '.html');
        }
    }

    /**
     * Process import for a specific module
     */
    private function processImport($modulo, $file)
    {
        // This is a simplified implementation - you would need to customize
        // for each module based on its specific fields and validation rules

        try {
            // Read CSV file
            if (($handle = fopen($file->getRealPath(), "r")) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ",");
                
                // Map module to model
                $modelMap = [
                    'alarmas' => 'App\\Models\\Alarma',
                    'bitacora' => 'App\\Models\\Bitacora',
                    'certificados-verificacion' => 'App\\Models\\CertificadoVerificacion',
                    'cfdi' => 'App\\Models\\Cfdi',
                    'contribuyentes' => 'App\\Models\\Contribuyente',
                    'dictamenes' => 'App\\Models\\Dictamen',
                    'dispensarios' => 'App\\Models\\Dispensario',
                    'existencias' => 'App\\Models\\Existencia',
                    'instalaciones' => 'App\\Models\\Instalacion',
                    'mangueras' => 'App\\Models\\Manguera',
                    'medidores' => 'App\\Models\\Medidor',
                    'pedimentos' => 'App\\Models\\Pedimento',
                    'permisos' => 'App\\Models\\Permission',
                    'productos' => 'App\\Models\\Producto',
                    'registros-volumetricos' => 'App\\Models\\RegistroVolumetrico',
                    'reportes-sat' => 'App\\Models\\ReporteSat',
                    'roles' => 'App\\Models\\Role',
                    'tanques' => 'App\\Models\\Tanque',
                    'users' => 'App\\Models\\User'
                ];

                $modelClass = $modelMap[$modulo] ?? null;

                if (!$modelClass) {
                    fclose($handle);
                    return ['success' => false, 'message' => 'Modelo no encontrado para el módulo'];
                }

                // Import each row
                $imported = 0;
                $errors = [];
                $rowNumber = 2; // Start at 2 because of header

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    try {
                        // Combine headers with data
                        $rowData = array_combine($headers, $data);
                        
                        // Create model instance and save
                        $model = new $modelClass();
                        $model->fill($rowData);
                        $model->save();
                        $imported++;
                    } catch (\Exception $e) {
                        $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                    }
                    $rowNumber++;
                }
                
                fclose($handle);

                return [
                    'success' => true,
                    'message' => "Importados $imported registros",
                    'imported' => $imported,
                    'errors' => $errors
                ];
            } else {
                return ['success' => false, 'message' => 'No se pudo abrir el archivo'];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ];
        }
    }
}