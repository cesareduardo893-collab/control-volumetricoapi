<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarmas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_registro');
            $table->datetime('fecha_hora');
            $table->string('componente_tipo');
            $table->unsignedBigInteger('componente_id')->nullable();
            $table->string('componente_identificador');
            $table->foreignId('tipo_alarma_id')->constrained('catalogo_valores')->onDelete('restrict');
            $table->string('gravedad', 20);
            $table->text('descripcion');
            $table->json('datos_contexto')->nullable();
            $table->decimal('diferencia_detectada', 12, 4)->nullable();
            $table->decimal('porcentaje_diferencia', 8, 4)->nullable();
            $table->decimal('limite_permitido', 8, 4)->nullable();
            $table->json('diagnostico_automatico')->nullable();
            $table->json('recomendaciones')->nullable();
            $table->boolean('atendida')->default(false);
            $table->datetime('fecha_atencion')->nullable();
            $table->foreignId('atendida_por')->nullable()->constrained('users')->onDelete('set null');
            $table->text('acciones_tomadas')->nullable();
            $table->enum('estado_atencion', ['PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'IGNORADA'])->default('PENDIENTE');
            $table->boolean('requiere_atencion_inmediata')->default(false);
            $table->datetime('fecha_limite_atencion')->nullable();
            $table->json('historial_cambios_estado')->nullable();
            $table->timestamps();
            
            $table->unique('numero_registro');
            $table->index('fecha_hora');
            $table->index('tipo_alarma_id');
            $table->index('gravedad');
            $table->index(['componente_tipo', 'componente_id']);
            $table->index('atendida');
            $table->index('estado_atencion');
            $table->index('requiere_atencion_inmediata');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarmas');
    }
};