<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modificar tabla tanques
        Schema::table('tanques', function (Blueprint $table) {
            $table->dropForeign(['instalacion_id']);
            $table->foreignId('instalacion_id')->nullable()->change();
            $table->foreign('instalacion_id')->references('id')->on('instalaciones')->onDelete('cascade');

            $table->foreignId('tipo_tanque_id')->nullable()->constrained('catalogo_valores')->onDelete('set null')->after('identificador');
            $table->string('placas')->nullable()->after('tipo_tanque_id');
            $table->string('numero_economico')->nullable()->after('placas');
        });

        // Tabla historial_conexiones
        Schema::create('historial_conexiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('fecha_hora');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('dispositivo')->nullable();
            $table->boolean('exitosa')->default(true);
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('fecha_hora');
            $table->index('exitosa');
        });

        // Tabla movimientos_dia
        Schema::create('movimientos_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('existencia_id')->constrained('existencias')->onDelete('cascade');
            $table->enum('tipo_movimiento', ['INICIAL', 'RECEPCION', 'ENTREGA', 'VENTA', 'TRASPASO', 'AJUSTE', 'INVENTARIO']);
            $table->decimal('volumen', 12, 4);
            $table->decimal('temperatura', 5, 2)->nullable();
            $table->decimal('presion', 8, 3)->nullable();
            $table->decimal('densidad', 10, 4)->nullable();
            $table->decimal('volumen_corregido', 12, 4);
            $table->string('documento_referencia')->nullable();
            $table->string('rfc_contraparte', 13)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index('existencia_id');
            $table->index('tipo_movimiento');
            $table->index('documento_referencia');
            $table->index('usuario_id');
        });

        // Tabla historial_calibraciones (tanques)
        Schema::create('historial_calibraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanque_id')->constrained('tanques')->onDelete('cascade');
            $table->date('fecha_calibracion');
            $table->date('fecha_proxima_calibracion')->nullable();
            $table->string('certificado_calibracion')->nullable();
            $table->string('entidad_calibracion')->nullable();
            $table->decimal('incertidumbre_medicion', 5, 3)->nullable();
            $table->json('tabla_aforo')->nullable();
            $table->json('curvas_calibracion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index('tanque_id');
            $table->index('fecha_calibracion');
            $table->index('fecha_proxima_calibracion');
            $table->index('usuario_id');
        });

        // Tabla historial_calibraciones_medidores (con índices de nombre corto)
        Schema::create('historial_calibraciones_medidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medidor_id')->constrained('medidores')->onDelete('cascade');
            $table->date('fecha_calibracion');
            $table->date('fecha_proxima_calibracion')->nullable();
            $table->string('certificado_calibracion')->nullable();
            $table->string('laboratorio_calibracion')->nullable();
            $table->decimal('incertidumbre_calibracion', 5, 3)->nullable();
            $table->decimal('precision', 5, 3);
            $table->decimal('repetibilidad', 5, 3)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            // Índices con nombres explícitos cortos
            $table->index('medidor_id', 'hc_med_medidor_idx');
            $table->index('fecha_calibracion', 'hc_med_fecha_cal_idx');
            $table->index('fecha_proxima_calibracion', 'hc_med_fecha_prox_idx');
            $table->index('usuario_id', 'hc_med_usuario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_calibraciones_medidores');
        Schema::dropIfExists('historial_calibraciones');
        Schema::dropIfExists('movimientos_dia');
        Schema::dropIfExists('historial_conexiones');

        Schema::table('tanques', function (Blueprint $table) {
            $table->dropForeign(['tipo_tanque_id']);
            $table->dropColumn(['tipo_tanque_id', 'placas', 'numero_economico']);

            $table->dropForeign(['instalacion_id']);
            $table->foreignId('instalacion_id')->nullable(false)->change();
            $table->foreign('instalacion_id')->references('id')->on('instalaciones')->onDelete('cascade');
        });
    }
};