<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index();
            $table->string('email');
            $table->BigInteger('user_id')->unsigned();
            $table->enum('status', ['pending', 'successful', 'canceled', 'expired'])->default('pending');
            $table->datetime('valid_till');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('user_invitations');
    }
};
