<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->onDelete('cascade');
            $table->foreignId('producto_id')->nullable()->constrained('productos')->onDelete('set null');
            $table->string('numero_serie')->nullable();
            $table->string('identificador');
            $table->string('modelo')->nullable();
            $table->string('fabricante')->nullable();
            $table->string('material', 100);
            $table->decimal('capacidad_total', 12, 4);
            $table->decimal('capacidad_util', 12, 4);
            $table->decimal('capacidad_operativa', 12, 4);
            $table->decimal('capacidad_minima', 12, 4)->default(0);
            $table->decimal('capacidad_gas_talon', 12, 4)->nullable();
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->date('fecha_ultima_calibracion')->nullable();
            $table->date('fecha_proxima_calibracion')->nullable();
            $table->string('certificado_calibracion')->nullable();
            $table->string('entidad_calibracion')->nullable();
            $table->decimal('incertidumbre_medicion', 5, 3)->nullable();
            $table->json('historial_calibraciones')->nullable();
            $table->decimal('temperatura_referencia', 5, 2)->default(20.00);
            $table->decimal('presion_referencia', 8, 3)->default(101.325);
            $table->enum('tipo_medicion', ['estatica', 'dinamica'])->default('estatica');
            $table->enum('estado', ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO', 'CALIBRACION'])->default('OPERATIVO');
            $table->json('tabla_aforo')->nullable();
            $table->json('curvas_calibracion')->nullable();
            $table->json('evidencias_alteracion')->nullable();
            $table->timestamp('ultima_deteccion_alteracion')->nullable();
            $table->boolean('alerta_alteracion')->default(false);
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['identificador', 'deleted_at']);
            $table->index('instalacion_id');
            $table->index('estado');
            $table->index('fecha_proxima_calibracion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanques');
    }
};