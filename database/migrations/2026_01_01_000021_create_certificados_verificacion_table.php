<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_verificacion', function (Blueprint $table) {
            $table->id();
            $table->string('folio');
            $table->foreignId('contribuyente_id')
                  ->constrained('contribuyentes')
                  ->onDelete('cascade');
            $table->string('proveedor_rfc', 13);
            $table->string('proveedor_nombre');
            $table->date('fecha_emision');
            $table->date('fecha_inicio_verificacion');
            $table->date('fecha_fin_verificacion');
            $table->enum('resultado', ['acreditado', 'no_acreditado']);
            $table->json('tabla_cumplimiento');
            $table->json('hallazgos')->nullable();
            $table->json('recomendaciones_especificas')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->string('archivo_xml')->nullable();
            $table->string('archivo_json')->nullable();
            $table->json('archivos_adicionales')->nullable();
            $table->boolean('vigente')->default(true);
            $table->date('fecha_caducidad')->nullable();
            $table->boolean('requiere_verificacion_extraordinaria')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['folio', 'deleted_at']);
            $table->index('contribuyente_id');
            $table->index('fecha_emision');
            $table->index('resultado');
            $table->index('vigente');
            $table->index('fecha_caducidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_verificacion');
    }
};