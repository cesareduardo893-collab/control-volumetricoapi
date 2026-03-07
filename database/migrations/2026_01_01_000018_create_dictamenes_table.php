<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictamenes', function (Blueprint $table) {
            $table->id();
            $table->string('folio');
            $table->string('numero_lote');
            $table->foreignId('contribuyente_id')->constrained()->onDelete('cascade');
            $table->string('laboratorio_rfc', 13);
            $table->string('laboratorio_nombre');
            $table->string('laboratorio_numero_acreditacion');
            $table->date('fecha_emision');
            $table->date('fecha_toma_muestra');
            $table->date('fecha_pruebas');
            $table->date('fecha_resultados');
            $table->foreignId('instalacion_id')->nullable()->constrained('instalaciones')->onDelete('set null');
            $table->string('ubicacion_muestra')->nullable();
            $table->foreignId('producto_id')->constrained();
            $table->decimal('volumen_muestra', 10, 2);
            $table->string('unidad_medida_muestra', 10);
            $table->string('metodo_muestreo');
            $table->string('metodo_ensayo');
            $table->json('metodos_aplicados')->nullable();
            $table->decimal('densidad_api', 5, 2)->nullable();
            $table->decimal('azufre', 5, 2)->nullable();
            $table->string('clasificacion_azufre')->nullable();
            $table->string('clasificacion_api')->nullable();
            $table->json('composicion_molar')->nullable();
            $table->json('propiedades_fisicas')->nullable();
            $table->json('propiedades_quimicas')->nullable();
            $table->decimal('poder_calorifico', 10, 4)->nullable();
            $table->decimal('poder_calorifico_superior', 10, 4)->nullable();
            $table->decimal('poder_calorifico_inferior', 10, 4)->nullable();
            $table->decimal('octanaje_ron', 5, 2)->nullable();
            $table->decimal('octanaje_mon', 5, 2)->nullable();
            $table->decimal('indice_octano', 5, 2)->nullable();
            $table->boolean('contiene_bioetanol')->default(false);
            $table->decimal('porcentaje_bioetanol', 5, 2)->nullable();
            $table->boolean('contiene_biodiesel')->default(false);
            $table->decimal('porcentaje_biodiesel', 5, 2)->nullable();
            $table->boolean('contiene_bioturbosina')->default(false);
            $table->decimal('porcentaje_bioturbosina', 5, 2)->nullable();
            $table->decimal('fame', 10, 2)->nullable();
            $table->decimal('porcentaje_propano', 5, 2)->nullable();
            $table->decimal('porcentaje_butano', 5, 2)->nullable();
            $table->decimal('propano_normalizado', 5, 2)->nullable();
            $table->decimal('butano_normalizado', 5, 2)->nullable();
            $table->json('composicion_normalizada')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->string('archivo_xml')->nullable();
            $table->string('archivo_json')->nullable();
            $table->json('archivos_adicionales')->nullable();
            $table->enum('estado', ['VIGENTE', 'CADUCADO', 'CANCELADO'])->default('VIGENTE');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['folio', 'deleted_at']);
            $table->unique(['numero_lote', 'deleted_at']);
            $table->index('contribuyente_id');
            $table->index('producto_id');
            $table->index('fecha_emision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictamenes');
    }
};