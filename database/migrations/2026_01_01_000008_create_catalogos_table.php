<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('clave');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['nombre', 'clave', 'deleted_at']);
            $table->index('nombre');
            $table->index('clave');
        });

        Schema::create('catalogo_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('catalogos')->onDelete('cascade');
            $table->string('valor');
            $table->string('clave');
            $table->string('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['catalogo_id', 'clave', 'deleted_at']);
            $table->index(['catalogo_id', 'valor']);
            $table->index('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_valores');
        Schema::dropIfExists('catalogos');
    }
};