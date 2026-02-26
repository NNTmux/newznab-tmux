<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BtcPaymentController extends BasePageController
{
    /**
     * BtcPay callback.
     */
    public function btcPayCallback(Request $request): Response
    {
        $hashCheck = 'sha256='.hash_hmac('sha256', $request->getContent(), config('nntmux.btcpay_webhook_secret'));
        if ($hashCheck !== $request->header('btcpay-sig')) {
            Log::channel('btc_payment')->error('BTCPay webhook hash check failed: '.$request->header('btcpay-sig'));

            return response('Not Found', 404);
        }
        $payload = json_decode($request->getContent(), true);
        // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
        if ($payload['type'] === 'InvoicePaymentSettled') {
            $user = User::query()->where('email', '=', $payload['metadata']['buyerEmail'])->first();
            if ($user) {
                $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', 'Settled')->first();
                if ($checkOrder !== null) {
                    Log::channel('btc_payment')->error('Duplicate BTCPay webhook: '.$payload['webhookId']);

                    return response('OK', 200);
                }

                Payment::create([
                    'email' => $payload['metadata']['buyerEmail'],
                    'username' => $user->username,
                    'item_description' => $payload['metadata']['itemDesc'],
                    'order_id' => $payload['metadata']['orderId'],
                    'payment_id' => $payload['payment']['id'],
                    'payment_status' => $payload['payment']['status'],
                    'invoice_amount' => $payload['metadata']['invoice_amount'],
                    'payment_method' => $payload['paymentMethodId'],
                    'payment_value' => $payload['payment']['value'],
                    'webhook_id' => $payload['webhookId'],
                    'invoice_id' => $payload['invoiceId'],
                ]);

                return response('OK', 200);
            }

            Log::channel('btc_payment')->error('User not found for BTCPay webhook: '.$payload['metadata']['buyerEmail']);

            return response('Not Found', 404);
        }

        if ($payload['type'] === 'InvoiceSettled') {
            // Check if we have the invoice_id in payments table and if we do, update the user account
            $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', 'Settled')->where(function ($query) {
                return $query->where('invoice_status', 'Pending')->orWhereNull('invoice_status');
            })->first();
            if ($checkOrder !== null) {
                $user = User::query()->where('email', '=', $checkOrder->email)->first();

                // If user not found and email ends with pm.me, try proton.me and protonmail.com
                if (! $user && str_ends_with($checkOrder->email, '@pm.me')) {
                    $emailLocalPart = substr($checkOrder->email, 0, -6); // Remove @pm.me
                    $user = User::query()->where('email', '=', $emailLocalPart.'@proton.me')->first();
                    if (! $user) {
                        $user = User::query()->where('email', '=', $emailLocalPart.'@protonmail.com')->first();
                    }
                }
                if ($user) {
                    // Extract role name and addYears from item_description using regex
                    // Matches patterns like "User 1", "Admin ++ 2", "Friend 3"
                    if (preg_match('/(?P<role>\w+(\s\+\+)?)[\s]+(?P<addYears>\d+)/i', $checkOrder->item_description, $matches)) {
                        $roleName = $matches['role'];
                        $addYears = (int) $matches['addYears'];
                    } else {
                        $roleName = $checkOrder->item_description;
                        $addYears = null;
                    }

                    User::updateUserRole($user->id, $roleName, addYears: $addYears);
                    $checkOrder->update(['invoice_status' => 'Settled']);
                    Log::channel('btc_payment')->info('User: '.$user->username.' upgraded to '.$roleName.' (+'.$addYears.' years) for BTCPay webhook: '.$checkOrder->webhook_id);

                    return response('OK', 200);
                }

                Log::channel('btc_payment')->error('User not found for BTCPay webhook: '.$checkOrder->webhook_id);

                return response('Not Found', 404);
            }
        }

        return response('OK', 200);
    }
}
