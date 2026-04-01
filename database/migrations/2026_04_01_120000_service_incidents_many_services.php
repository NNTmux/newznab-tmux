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
        Schema::create('service_incident_service_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_incident_id')->constrained('service_incidents')->cascadeOnDelete();
            $table->foreignId('service_status_id')->constrained('service_statuses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_incident_id', 'service_status_id'], 'svc_incident_svc_pair_unique');
            $table->index('service_status_id');
        });

        $rows = DB::table('service_incidents')->select(['id', 'service_status_id'])->get();
        $now = now();
        foreach ($rows as $row) {
            DB::table('service_incident_service_status')->insert([
                'service_incident_id' => $row->id,
                'service_status_id' => $row->service_status_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('service_incidents', function (Blueprint $table) {
            $table->dropForeign(['service_status_id']);
            $table->dropColumn('service_status_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores a single service_status_id per incident (first pivot row only). Multi-service data is lost on rollback.
     */
    public function down(): void
    {
        Schema::table('service_incidents', function (Blueprint $table) {
            $table->foreignId('service_status_id')->after('impact')->nullable()->constrained('service_statuses')->cascadeOnDelete();
        });

        $rows = DB::table('service_incident_service_status')
            ->select(['service_incident_id', 'service_status_id'])
            ->orderBy('id')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->service_incident_id])) {
                continue;
            }
            $seen[$row->service_incident_id] = true;
            DB::table('service_incidents')
                ->where('id', $row->service_incident_id)
                ->update(['service_status_id' => $row->service_status_id]);
        }

        Schema::dropIfExists('service_incident_service_status');
    }
};
