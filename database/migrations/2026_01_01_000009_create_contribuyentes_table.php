<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->string('rfc', 13);
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('regimen_fiscal');
            $table->string('domicilio_fiscal');
            $table->string('codigo_postal', 5);
            $table->string('telefono', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('representante_rfc', 13)->nullable();
            $table->foreignId('caracter_actua_id')->nullable()->constrained('catalogo_valores')->onDelete('set null');
            $table->string('numero_permiso')->nullable();
            $table->string('tipo_permiso')->nullable();
            $table->string('proveedor_equipos_rfc', 13)->nullable();
            $table->string('proveedor_equipos_nombre')->nullable();
            $table->json('certificados_vigentes')->nullable();
            $table->date('ultima_verificacion')->nullable();
            $table->date('proxima_verificacion')->nullable();
            $table->string('estatus_verificacion')->nullable();
            $table->boolean('activo')->default(true);
            // Corrección: usar CURRENT_DATE para que sea dinámico al insertar
            $table->date('fecha_registro')->default(DB::raw('CURRENT_DATE'));
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['rfc', 'deleted_at']);
            $table->index('razon_social');
            $table->index('numero_permiso');
            $table->index('proxima_verificacion');
            $table->index('caracter_actua_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribuyentes');
    }
};