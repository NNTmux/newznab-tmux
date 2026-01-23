<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to ensure exact column type matching with existing tables
        DB::statement("
            CREATE TABLE `user_excluded_categories` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `users_id` int(10) unsigned NOT NULL,
                `categories_id` int(10) unsigned NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ux_user_excluded_categories` (`users_id`, `categories_id`),
                KEY `ix_user_excluded_categories_users_id` (`users_id`),
                CONSTRAINT `FK_uec_users` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `FK_uec_categories` FOREIGN KEY (`categories_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_excluded_categories');
    }
};
