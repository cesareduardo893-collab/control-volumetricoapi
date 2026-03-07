<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->json('permisos_detallados')->nullable();
            $table->integer('nivel_jerarquico')->default(0);
            $table->boolean('es_administrador')->default(false);
            $table->json('restricciones_acceso')->nullable();
            $table->json('configuracion_ui')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['nombre', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};