<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identificacion', 18);
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            $table->integer('login_attempts')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_expires_at')->nullable();
            $table->timestamp('last_password_change')->nullable();
            $table->boolean('force_password_change')->default(false);
            $table->string('session_token', 100)->nullable();
            $table->timestamp('session_expires_at')->nullable();
            
            $table->string('last_login_ip', 45)->nullable();
            $table->text('last_login_user_agent')->nullable();
            $table->json('dispositivos_autorizados')->nullable();
            $table->json('historial_conexiones')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['identificacion', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);
            $table->index('activo');
            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};