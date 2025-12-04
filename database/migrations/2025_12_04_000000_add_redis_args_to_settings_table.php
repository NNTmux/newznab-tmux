<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add redis_args setting if it doesn't exist
        $exists = DB::table('settings')->where('name', 'redis_args')->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'name' => 'redis_args',
                'value' => 'NULL',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('name', 'redis_args')->delete();
    }
};

