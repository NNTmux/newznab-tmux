<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Payment>
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state: a settled BTC payment for the cheapest plan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'item_description' => 'Supporter 1 year',
            'order_id' => Str::random(22),
            'payment_id' => Str::random(66),
            'payment_status' => Payment::PAYMENT_STATUS_SETTLED,
            'invoice_status' => Payment::INVOICE_STATUS_SETTLED,
            'invoice_amount' => '10',
            'payment_method' => 'BTC-CHAIN',
            'payment_value' => '0.00012345',
            'webhook_id' => Str::random(22),
            'invoice_id' => Str::random(22),
        ];
    }

    /**
     * Paid, but the role upgrade has not been applied.
     */
    public function unsettled(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_status' => Payment::INVOICE_STATUS_PENDING,
        ]);
    }

    /**
     * Recorded before invoice settlement was tracked, so still Pending.
     */
    public function legacy(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_status' => Payment::INVOICE_STATUS_PENDING,
            'created_at' => '2024-06-01 12:00:00',
            'updated_at' => '2024-06-01 12:00:00',
        ]);
    }

    /**
     * Set the BTCPay payment method id and the amount of coin received.
     */
    public function method(string $method, string $value = '1'): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => $method,
            'payment_value' => $value,
        ]);
    }

    /**
     * Set the purchased plan and its invoice price.
     */
    public function plan(string $description, string $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'item_description' => $description,
            'invoice_amount' => $amount,
        ]);
    }

    /**
     * Record the payment against an existing user.
     */
    public function paidBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => $user->username,
            'email' => $user->email,
        ]);
    }

    /**
     * Record the payment at a given time.
     */
    public function paidAt(\DateTimeInterface|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }
}
