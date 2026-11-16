<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    #[Test]
    #[DataProvider('paymentMethods')]
    public function it_maps_a_payment_method_to_its_coin(?string $method, string $coin): void
    {
        $this->assertSame($coin, Payment::coinFor($method));
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function paymentMethods(): array
    {
        return [
            'legacy method id' => ['BTC', 'BTC'],
            'on-chain method id' => ['BTC-CHAIN', 'BTC'],
            'lightning method id' => ['btc-LightningNetwork', 'BTC'],
            'liquid is its own coin' => ['LBTC-CHAIN', 'LBTC'],
            'other coin' => ['DOGE-CHAIN', 'DOGE'],
            'empty method' => ['', 'Unknown'],
            'missing method' => [null, 'Unknown'],
        ];
    }

    #[Test]
    #[DataProvider('invoiceStates')]
    public function it_derives_the_invoice_state_from_status_and_age(?string $invoiceStatus, ?string $createdAt, string $state): void
    {
        $payment = new Payment(['invoice_status' => $invoiceStatus, 'created_at' => $createdAt]);

        $this->assertSame($state, $payment->invoiceState());
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function invoiceStates(): array
    {
        return [
            'settled' => ['Settled', '2026-01-01 10:00:00', Payment::STATE_SETTLED],
            'settled before tracking' => ['Settled', '2024-02-13 10:00:00', Payment::STATE_SETTLED],
            'pending before tracking is legacy' => ['Pending', '2024-11-16 11:46:39', Payment::STATE_LEGACY],
            'pending once tracking started is unsettled' => ['Pending', '2024-11-16 11:46:40', Payment::STATE_UNSETTLED],
            'pending today is unsettled' => ['Pending', '2026-11-16 09:00:00', Payment::STATE_UNSETTLED],
            'missing status is unsettled' => [null, '2026-11-16 09:00:00', Payment::STATE_UNSETTLED],
            'missing date is unsettled' => ['Pending', null, Payment::STATE_UNSETTLED],
        ];
    }
}
