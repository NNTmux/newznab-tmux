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
        Schema::table('service_statuses', static function (Blueprint $table): void {
            $table->string('check_type')->default('http')->after('endpoint_url');
            $table->string('probe_identifier')->nullable()->after('check_type');

            $table->index('check_type');
            $table->index('probe_identifier');
        });

        DB::table('service_statuses')->update([
            'check_type' => 'http',
            'probe_identifier' => null,
        ]);

        $now = now();
        $nextOrder = (int) DB::table('service_statuses')->max('sort_order') + 1;

        $probeServices = [
            ['name' => 'Database', 'slug' => 'database', 'probe_identifier' => 'database'],
            ['name' => 'Redis', 'slug' => 'redis', 'probe_identifier' => 'redis'],
            ['name' => 'Search Engine', 'slug' => 'search', 'probe_identifier' => 'search'],
            ['name' => 'NNTP', 'slug' => 'nntp', 'probe_identifier' => 'nntp'],
            ['name' => 'Queue', 'slug' => 'queue', 'probe_identifier' => 'queue'],
            ['name' => 'Disk Space', 'slug' => 'disk', 'probe_identifier' => 'disk'],
        ];

        foreach ($probeServices as $service) {
            DB::table('service_statuses')->updateOrInsert(
                ['slug' => $service['slug']],
                [
                    'name' => $service['name'],
                    'endpoint_url' => null,
                    'check_type' => 'probe',
                    'probe_identifier' => $service['probe_identifier'],
                    'status' => 'operational',
                    'is_enabled' => true,
                    'sort_order' => $nextOrder++,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('service_statuses')
            ->whereIn('slug', ['database', 'redis', 'search', 'nntp', 'queue', 'disk'])
            ->delete();

        Schema::table('service_statuses', static function (Blueprint $table): void {
            $table->dropIndex(['check_type']);
            $table->dropIndex(['probe_identifier']);
            $table->dropColumn(['check_type', 'probe_identifier']);
        });
    }
};
