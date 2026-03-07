<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('clave_sat', 10);
            $table->string('codigo', 20);
            $table->string('clave_identificacion', 10);
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('unidad_medida', 50);
            $table->enum('tipo_hidrocarburo', ['petroleo', 'gas_natural', 'condensados', 'gasolina', 'diesel', 'turbosina', 'gas_lp', 'propano', 'otro']);
            $table->decimal('densidad_api', 5, 2)->nullable();
            $table->decimal('contenido_azufre', 5, 2)->nullable();
            $table->string('clasificacion_azufre')->nullable();
            $table->string('clasificacion_api')->nullable();
            $table->decimal('poder_calorifico', 10, 4)->nullable();
            $table->json('composicion_tipica')->nullable();
            $table->json('especificaciones_tecnicas')->nullable();
            $table->decimal('octanaje_ron', 5, 2)->nullable();
            $table->decimal('octanaje_mon', 5, 2)->nullable();
            $table->decimal('indice_octano', 5, 2)->nullable();
            $table->boolean('contiene_bioetanol')->default(false);
            $table->decimal('porcentaje_bioetanol', 5, 2)->nullable();
            $table->boolean('contiene_biodiesel')->default(false);
            $table->decimal('porcentaje_biodiesel', 5, 2)->nullable();
            $table->boolean('contiene_bioturbosina')->default(false);
            $table->decimal('porcentaje_bioturbosina', 5, 2)->nullable();
            $table->decimal('fame', 10, 2)->nullable();
            $table->decimal('porcentaje_propano', 5, 2)->nullable();
            $table->decimal('porcentaje_butano', 5, 2)->nullable();
            $table->decimal('propano_normalizado', 5, 2)->nullable();
            $table->decimal('butano_normalizado', 5, 2)->nullable();
            $table->decimal('indice_wobbe', 10, 4)->nullable();
            $table->string('clasificacion_gas')->nullable();
            $table->string('color_identificacion', 7)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['clave_sat', 'deleted_at']);
            $table->unique(['codigo', 'deleted_at']);
            $table->unique(['clave_identificacion', 'deleted_at']);
            $table->index('tipo_hidrocarburo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};