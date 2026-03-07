<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->string('numero_registro');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('tipo_evento', [
                'administracion_sistema',
                'eventos_ucc',
                'eventos_programas',
                'eventos_comunicacion',
                'operaciones_cotidianas',
                'verificaciones_autoridad',
                'inconsistencias_volumetricas',
                'seguridad'
            ]);
            $table->string('subtipo_evento');
            $table->string('modulo');
            $table->string('tabla')->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->text('descripcion');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('dispositivo')->nullable();
            $table->json('metadatos_seguridad')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('hash_anterior', 64)->nullable();
            $table->string('hash_actual', 64);
            $table->string('firma_digital')->nullable();
            $table->timestamps();
            
            $table->unique('numero_registro');
            $table->index('tipo_evento');
            $table->index(['modulo', 'registro_id']);
            $table->index('usuario_id');
            $table->index('created_at');
            $table->index('hash_actual');
        });
        
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('
                CREATE TABLE IF NOT EXISTS bitacora_hash_sequence (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    last_hash VARCHAR(64)
                )
            ');
            DB::unprepared('
                INSERT IGNORE INTO bitacora_hash_sequence (id, last_hash) VALUES (1, NULL)
            ');
            DB::unprepared('
                CREATE TRIGGER prevent_bitacora_update BEFORE UPDATE ON bitacora
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "No se pueden modificar registros de bitácora";
                END
            ');
            DB::unprepared('
                CREATE TRIGGER prevent_bitacora_delete BEFORE DELETE ON bitacora
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "No se pueden eliminar registros de bitácora";
                END
            ');
            DB::unprepared('
                CREATE TRIGGER generate_bitacora_hash BEFORE INSERT ON bitacora
                FOR EACH ROW
                BEGIN
                    DECLARE last_hash VARCHAR(64);
                    SELECT last_hash INTO last_hash FROM bitacora_hash_sequence WHERE id = 1 FOR UPDATE;
                    SET NEW.hash_anterior = last_hash;
                    SET NEW.hash_actual = SHA2(CONCAT(COALESCE(last_hash, ""), NEW.descripcion, NOW()), 256);
                    UPDATE bitacora_hash_sequence SET last_hash = NEW.hash_actual WHERE id = 1;
                END
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bitacora_update');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bitacora_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS generate_bitacora_hash');
            DB::unprepared('DROP TABLE IF EXISTS bitacora_hash_sequence');
        }
        Schema::dropIfExists('bitacora');
    }
};