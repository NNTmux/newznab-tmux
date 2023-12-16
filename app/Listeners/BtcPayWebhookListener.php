<?php

namespace App\Listeners;

use App\Models\User;
use BTCPayServer\Client\Invoice;
use BTCPayServer\Exception\BTCPayException;
use Illuminate\Support\Facades\Log;
use Petzsch\LaravelBtcpay\Constants\BtcPayConstants;
use Petzsch\LaravelBtcpay\Events\BtcpayWebhookReceived;
use Petzsch\LaravelBtcpay\LaravelBtcpay;

class BtcPayWebhookListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BtcpayWebhookReceived $event): void
    {
        $payload = $event->payload;
        if ($payload['event']['code'] === BtcPayConstants::INVOICE_WEBHOOK_CODES) {
            // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
            try {
                $invoice = LaravelBtcpay::getInvoice($payload['data']['id']);
                $invoice_status = $invoice->getStatus();
                if ($invoice_status === 'Settled') {
                    preg_match('/(?P<role>\w+(\+\+)?)[ ](?P<addYears>\d+)/i', $invoice->getData()['metadata']['itemDesc'], $matches);
                    $user = User::query()->where('email', '=', $invoice->getData()['metadata']['buyerEmail'])->first();
                    if ($user) {
                        User::updateUserRole($user->id, $matches['role']);
                        User::updateUserRoleChangeDate($user->id, null, $matches['addYears']);
                    }
                }
            } catch (BTCPayException $e) {
                Log::error($e->getMessage());
            }
        }
    }
}
