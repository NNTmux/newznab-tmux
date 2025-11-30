<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->datetime('pending_role_start_date')->nullable()->after('rolechangedate')->comment('When the pending role change takes effect');
            $table->integer('pending_roles_id')->nullable()->after('pending_role_start_date')->comment('The role that will be applied after current role expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_role_start_date', 'pending_roles_id']);
        });
    }
};

