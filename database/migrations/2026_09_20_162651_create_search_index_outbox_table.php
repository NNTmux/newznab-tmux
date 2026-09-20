<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 16);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id', 'id'], 'search_outbox_entity_idx');
            $table->index(['entity_type', 'id'], 'search_outbox_batch_idx');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER releases_search_outbox_after_insert
                AFTER INSERT ON releases
                FOR EACH ROW
                INSERT INTO search_index_outbox (entity_type, entity_id, action, payload, created_at)
                VALUES ('release', NEW.id, 'upsert', NULL, CURRENT_TIMESTAMP)
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER releases_search_outbox_after_update
                AFTER UPDATE ON releases
                FOR EACH ROW
                INSERT INTO search_index_outbox (entity_type, entity_id, action, payload, created_at)
                VALUES ('release', NEW.id, 'upsert', NULL, CURRENT_TIMESTAMP)
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER releases_search_outbox_after_delete
                AFTER DELETE ON releases
                FOR EACH ROW
                INSERT INTO search_index_outbox (entity_type, entity_id, action, payload, created_at)
                VALUES ('release', OLD.id, 'delete', NULL, CURRENT_TIMESTAMP)
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS releases_search_outbox_after_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS releases_search_outbox_after_update');
            DB::unprepared('DROP TRIGGER IF EXISTS releases_search_outbox_after_delete');
        }

        Schema::dropIfExists('search_index_outbox');
    }
};
