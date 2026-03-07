<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_volumetricos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_registro');
            $table->foreignId('instalacion_id')->constrained('instalaciones')->onDelete('cascade');
            $table->foreignId('tanque_id')->constrained('tanques')->onDelete('cascade');
            $table->foreignId('medidor_id')->nullable()->constrained('medidores')->onDelete('set null');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
            $table->foreignId('usuario_registro_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('usuario_valida_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->decimal('volumen_inicial', 12, 4);
            $table->decimal('volumen_final', 12, 4);
            $table->decimal('volumen_operacion', 12, 4);
            $table->decimal('temperatura_inicial', 5, 2);
            $table->decimal('temperatura_final', 5, 2);
            $table->decimal('presion_inicial', 8, 3)->nullable();
            $table->decimal('presion_final', 8, 3)->nullable();
            $table->decimal('densidad', 10, 4);
            $table->decimal('volumen_corregido', 12, 4);
            $table->decimal('factor_correccion', 8, 6);
            $table->json('detalle_correccion')->nullable();
            $table->decimal('masa', 12, 4)->nullable();
            $table->decimal('poder_calorifico', 10, 4)->nullable();
            $table->decimal('energia_total', 14, 4)->nullable();
            $table->enum('tipo_registro', ['operacion', 'acumulado', 'existencias']);
            $table->enum('operacion', ['recepcion', 'entrega', 'inventario_inicial', 'inventario_final', 'venta']);
            $table->string('rfc_contraparte', 13)->nullable();
            $table->string('documento_fiscal_uuid', 36)->nullable();
            $table->string('folio_fiscal')->nullable();
            $table->string('tipo_cfdi')->nullable();
            $table->enum('estado', ['PENDIENTE', 'PROCESADO', 'VALIDADO', 'ERROR', 'CANCELADO', 'CON_ALARMA'])->default('PENDIENTE');
            $table->timestamp('fecha_validacion')->nullable();
            $table->json('validaciones_realizadas')->nullable();
            $table->json('inconsistencias_detectadas')->nullable();
            $table->decimal('porcentaje_diferencia', 8, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->text('errores')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['numero_registro', 'deleted_at']);
            $table->index(['fecha', 'instalacion_id']);
            $table->index('tipo_registro');
            $table->index('operacion');
            $table->index('estado');
            $table->index('documento_fiscal_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_volumetricos');
    }
};