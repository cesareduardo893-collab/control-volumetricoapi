<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_sat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->onDelete('cascade');
            $table->foreignId('usuario_genera_id')->constrained('users')->onDelete('restrict');
            $table->string('folio');
            $table->string('periodo', 7);
            $table->enum('tipo_reporte', ['MENSUAL', 'ANUAL', 'ESPECIAL']);
            $table->string('ruta_xml')->nullable();
            $table->string('ruta_pdf')->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->text('cadena_original')->nullable();
            $table->text('sello_digital')->nullable();
            $table->string('certificado_sat')->nullable();
            $table->timestamp('fecha_firma')->nullable();
            $table->json('datos_firma')->nullable();
            $table->string('folio_firma', 36)->nullable();
            $table->enum('estado', [
                'PENDIENTE', 'GENERADO', 'FIRMADO', 'ENVIADO', 
                'ACEPTADO', 'RECHAZADO', 'ERROR', 'REQUIERE_REENVIO'
            ])->default('PENDIENTE');
            $table->date('fecha_generacion')->nullable();
            $table->date('fecha_envio')->nullable();
            $table->string('acuse_sat')->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->json('detalle_respuesta')->nullable();
            $table->json('datos_reporte')->nullable();
            $table->json('detalle_errores')->nullable();
            $table->integer('numero_intentos')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['folio', 'deleted_at']);
            $table->index(['periodo', 'instalacion_id']);
            $table->index('tipo_reporte');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_sat');
    }
};