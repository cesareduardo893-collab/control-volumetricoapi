<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();
            $table->string('numero_registro');
            $table->foreignId('tanque_id')->constrained()->onDelete('cascade');
            $table->foreignId('producto_id')->constrained()->onDelete('restrict');
            $table->date('fecha');
            $table->time('hora');
            $table->decimal('volumen_medido', 12, 4);
            $table->decimal('temperatura', 5, 2);
            $table->decimal('presion', 8, 3)->nullable();
            $table->decimal('densidad', 10, 4)->nullable();
            $table->decimal('volumen_corregido', 12, 4);
            $table->decimal('factor_correccion_temperatura', 8, 6);
            $table->decimal('factor_correccion_presion', 8, 6)->default(1);
            $table->decimal('volumen_disponible', 12, 4);
            $table->decimal('volumen_agua', 10, 4)->default(0);
            $table->decimal('volumen_sedimentos', 10, 4)->default(0);
            $table->decimal('volumen_inicial_dia', 12, 4)->nullable();
            $table->decimal('volumen_calculado', 12, 4)->nullable();
            $table->decimal('diferencia_volumen', 10, 4)->nullable();
            $table->decimal('porcentaje_diferencia', 8, 4)->nullable();
            $table->json('detalle_calculo')->nullable();
            $table->json('movimientos_dia')->nullable();
            $table->enum('tipo_registro', ['inicial', 'operacion', 'final'])->default('operacion');
            $table->enum('tipo_movimiento', ['INICIAL', 'RECEPCION', 'ENTREGA', 'VENTA', 'TRASPASO', 'AJUSTE', 'INVENTARIO']);
            $table->string('documento_referencia')->nullable();
            $table->string('rfc_contraparte', 13)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_registro_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('usuario_valida_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('fecha_validacion')->nullable();
            $table->enum('estado', ['PENDIENTE', 'VALIDADO', 'EN_REVISION', 'CON_ALARMA'])->default('PENDIENTE');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['numero_registro', 'deleted_at']);
            $table->index(['fecha', 'tanque_id']);
            $table->index('tipo_movimiento');
            $table->index('estado');
            $table->index('producto_id');
            $table->index('usuario_registro_id');
            $table->index('usuario_valida_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('existencias');
    }
};