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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('email');
            $table->string('username');
            $table->string('item_description');
            $table->string('order_id');
            $table->string('payment_id');
            $table->string('payment_status');
            $table->string('invoice_amount');
            $table->string('payment_method');
            $table->string('payment_value');
            $table->string('webhook_id');
            $table->string('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
