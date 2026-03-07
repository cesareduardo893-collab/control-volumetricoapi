<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedimentos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pedimento');
            $table->foreignId('contribuyente_id')->constrained()->onDelete('cascade');
            $table->foreignId('producto_id')->constrained()->onDelete('restrict');
            $table->string('punto_exportacion')->nullable();
            $table->string('punto_internacion')->nullable();
            $table->string('pais_destino');
            $table->string('pais_origen');
            $table->string('medio_transporte_entrada');
            $table->string('medio_transporte_salida')->nullable();
            $table->string('incoterms', 10);
            $table->decimal('volumen', 12, 4);
            $table->string('unidad_medida', 10);
            $table->decimal('valor_comercial', 14, 4);
            $table->string('moneda', 3)->default('USD');
            $table->date('fecha_pedimento');
            $table->date('fecha_arribo')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->foreignId('registro_volumetrico_id')->nullable()
                  ->constrained('registros_volumetricos')
                  ->onDelete('set null');
            $table->enum('estado', ['ACTIVO', 'UTILIZADO', 'CANCELADO'])->default('ACTIVO');
            $table->json('metadatos_aduana')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['numero_pedimento', 'deleted_at']);
            $table->index('contribuyente_id');
            $table->index('fecha_pedimento');
            $table->index('registro_volumetrico_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedimentos');
    }
};