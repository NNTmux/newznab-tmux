<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The release inform API that filled this table was removed.
     */
    public function up(): void
    {
        Schema::dropIfExists('release_informs');
    }

    public function down(): void
    {
        if (Schema::hasTable('release_informs')) {
            return;
        }

        Schema::create('release_informs', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('relOName');
            $table->string('relPName');
            $table->string('api_token');
        });
    }
};
