<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('asignado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamp('fecha_revocacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'role_id', 'fecha_revocacion'], 'user_role_activo_idx');
            $table->index('user_id');
            $table->index('role_id');
            $table->index('asignado_por');
        });
        
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('
                CREATE TRIGGER prevent_duplicate_active_user_role BEFORE INSERT ON user_role
                FOR EACH ROW
                BEGIN
                    DECLARE active_count INT;
                    IF NEW.fecha_revocacion IS NULL THEN
                        SELECT COUNT(*) INTO active_count 
                        FROM user_role 
                        WHERE user_id = NEW.user_id 
                          AND role_id = NEW.role_id 
                          AND fecha_revocacion IS NULL
                          AND deleted_at IS NULL;
                        IF active_count > 0 THEN
                            SIGNAL SQLSTATE "45000" 
                            SET MESSAGE_TEXT = "Ya existe una asignación activa para este usuario y rol";
                        END IF;
                    END IF;
                END
            ');
            
            DB::unprepared('
                CREATE TRIGGER prevent_duplicate_active_user_role_update BEFORE UPDATE ON user_role
                FOR EACH ROW
                BEGIN
                    DECLARE active_count INT;
                    IF NEW.fecha_revocacion IS NULL AND OLD.fecha_revocacion IS NOT NULL THEN
                        SELECT COUNT(*) INTO active_count 
                        FROM user_role 
                        WHERE user_id = NEW.user_id 
                          AND role_id = NEW.role_id 
                          AND fecha_revocacion IS NULL
                          AND deleted_at IS NULL
                          AND id != NEW.id;
                        IF active_count > 0 THEN
                            SIGNAL SQLSTATE "45000" 
                            SET MESSAGE_TEXT = "Ya existe una asignación activa para este usuario y rol";
                        END IF;
                    END IF;
                END
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_duplicate_active_user_role');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_duplicate_active_user_role_update');
        }
        Schema::dropIfExists('user_role');
    }
};