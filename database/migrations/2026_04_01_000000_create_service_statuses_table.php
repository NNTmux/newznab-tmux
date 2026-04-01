<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status');
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('uptime_percentage', 5, 2)->default('100.00');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index(['is_enabled', 'sort_order']);
        });

        $now = now();

        DB::table('service_statuses')->insert([
            [
                'name' => 'API',
                'slug' => 'api',
                'status' => 'operational',
                'last_checked_at' => null,
                'uptime_percentage' => '100.00',
                'response_time_ms' => null,
                'is_enabled' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'HTTP',
                'slug' => 'http',
                'status' => 'operational',
                'last_checked_at' => null,
                'uptime_percentage' => '100.00',
                'response_time_ms' => null,
                'is_enabled' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'RSS',
                'slug' => 'rss',
                'status' => 'operational',
                'last_checked_at' => null,
                'uptime_percentage' => '100.00',
                'response_time_ms' => null,
                'is_enabled' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_statuses');
    }
};
