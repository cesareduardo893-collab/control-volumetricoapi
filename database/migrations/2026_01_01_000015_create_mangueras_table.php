<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mangueras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispensario_id')
                  ->constrained('dispensarios')
                  ->onDelete('cascade');
            $table->string('clave')->unique();
            $table->string('descripcion')->nullable();
            $table->foreignId('medidor_id')
                  ->nullable()
                  ->constrained('medidores')
                  ->onDelete('set null');
            $table->enum('estado', ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO'])
                  ->default('OPERATIVO');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('dispensario_id');
            $table->index('medidor_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mangueras');
    }
};