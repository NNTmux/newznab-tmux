<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessBtcPayWebhook extends ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
        if ($payload['type'] === 'InvoiceReceivedPayment') {
            preg_match('/(?P<role>\w+(\+\+)?)[ ](?P<addYears>\d+)/i', $payload['metadata']['itemDesc'], $matches);
            $user = User::query()->where('email', '=', $payload['metadata']['buyerEmail'])->first();
            if ($user) {
                User::updateUserRole($user->id, $matches['role']);
                User::updateUserRoleChangeDate($user->id, null, $matches['addYears']);
                Log::info('User upgraded to '.$matches['role'].' for BTCPay webhook: '.$payload['metadata']['buyerEmail']);
            } else {
                Log::error('User not found for BTCPay webhook: '.$payload['metadata']['buyerEmail']);

            }
        }

    }
}
