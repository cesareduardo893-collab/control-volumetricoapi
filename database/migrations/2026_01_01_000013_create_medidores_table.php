<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanque_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('instalacion_id')->constrained('instalaciones')->onDelete('cascade');
            $table->string('numero_serie');
            $table->string('clave');
            $table->string('modelo')->nullable();
            $table->string('fabricante')->nullable();
            $table->enum('elemento_tipo', ['primario', 'secundario', 'terciario'])->default('primario');
            $table->enum('tipo_medicion', ['estatica', 'dinamica']);
            $table->foreignId('tecnologia_id')->nullable()->constrained('catalogo_valores')->onDelete('set null');
            $table->decimal('precision', 5, 3);
            $table->decimal('repetibilidad', 5, 3)->nullable();
            $table->decimal('capacidad_maxima', 12, 4);
            $table->decimal('capacidad_minima', 12, 4)->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->string('ubicacion_fisica')->nullable();
            $table->date('fecha_ultima_calibracion')->nullable();
            $table->date('fecha_proxima_calibracion')->nullable();
            $table->string('certificado_calibracion')->nullable();
            $table->string('laboratorio_calibracion')->nullable();
            $table->decimal('incertidumbre_calibracion', 5, 3)->nullable();
            $table->json('historial_calibraciones')->nullable();
            $table->enum('protocolo_comunicacion', ['modbus', 'opc', 'serial', 'ethernet', 'wireless', 'otros'])->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->integer('puerto_comunicacion')->nullable();
            $table->json('parametros_conexion')->nullable();
            $table->json('mecanismos_seguridad')->nullable();
            $table->json('evidencias_alteracion')->nullable();
            $table->timestamp('ultima_deteccion_alteracion')->nullable();
            $table->boolean('alerta_alteracion')->default(false);
            $table->json('historial_desconexiones')->nullable();
            $table->enum('estado', ['OPERATIVO', 'CALIBRACION', 'MANTENIMIENTO', 'FUERA_SERVICIO', 'FALLA_COMUNICACION'])->default('OPERATIVO');
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['numero_serie', 'deleted_at']);
            $table->unique(['clave', 'deleted_at']);
            
            $table->index('tanque_id');
            $table->index('instalacion_id');
            $table->index('estado');
            $table->index('fecha_proxima_calibracion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medidores');
    }
};