<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instalacion_id')
                  ->constrained('instalaciones')
                  ->onDelete('cascade');
            $table->string('clave')->unique();
            $table->string('descripcion')->nullable();
            $table->string('modelo')->nullable();
            $table->string('fabricante')->nullable();
            $table->enum('estado', ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO'])
                  ->default('OPERATIVO');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('instalacion_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensarios');
    }
};