<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfdi', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36);
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor')->nullable();
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor')->nullable();
            $table->enum('tipo_operacion', ['adquisicion', 'enajenacion', 'servicio']);
            $table->foreignId('producto_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('volumen', 12, 4)->nullable();
            $table->string('unidad_medida', 10)->nullable();
            $table->decimal('precio_unitario', 12, 4)->nullable();
            $table->decimal('subtotal', 14, 4);
            $table->decimal('iva', 14, 4)->default(0);
            $table->decimal('ieps', 14, 4)->default(0);
            $table->decimal('total', 14, 4);
            $table->string('tipo_servicio')->nullable();
            $table->text('descripcion_servicio')->nullable();
            $table->datetime('fecha_emision');
            $table->datetime('fecha_certificacion')->nullable();
            $table->foreignId('registro_volumetrico_id')->nullable()
                  ->constrained('registros_volumetricos')
                  ->onDelete('set null');
            $table->text('xml')->nullable();
            $table->string('ruta_xml')->nullable();
            $table->json('metadatos')->nullable();
            $table->enum('estado', ['VIGENTE', 'CANCELADO'])->default('VIGENTE');
            $table->date('fecha_cancelacion')->nullable();
            $table->string('uuid_relacionado', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['uuid', 'deleted_at']);
            $table->index('rfc_emisor');
            $table->index('rfc_receptor');
            $table->index('fecha_emision');
            $table->index('tipo_operacion');
            $table->index('registro_volumetrico_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfdi');
    }
};