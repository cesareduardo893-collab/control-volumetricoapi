<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instalaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribuyente_id')->constrained()->onDelete('cascade');
            $table->string('clave_instalacion');
            $table->string('nombre');
            $table->string('tipo_instalacion');
            $table->string('domicilio');
            $table->string('codigo_postal', 5);
            $table->string('municipio');
            $table->string('estado');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('responsable')->nullable();
            $table->date('fecha_operacion')->nullable();
            $table->enum('estatus', ['OPERACION', 'SUSPENDIDA', 'CANCELADA'])->default('OPERACION');
            $table->json('configuracion_monitoreo')->nullable();
            $table->json('parametros_volumetricos')->nullable();
            $table->json('umbrales_alarma')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['clave_instalacion', 'deleted_at']);
            $table->index('contribuyente_id');
            $table->index('estatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalaciones');
    }
};