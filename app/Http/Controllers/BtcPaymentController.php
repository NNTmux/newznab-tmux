<?php

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
            Log::error('BTCPay webhook hash check failed: '.$request->header('btcpay-sig'));

            return response('Not Found', 404);
        }
        $payload = json_decode($request->getContent(), true);
        // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
        if ($payload['type'] === 'InvoicePaymentSettled') {
            $user = User::query()->where('email', '=', $payload['metadata']['buyerEmail'])->first();
            if ($user) {
                $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', 'Settled')->first();
                if (! empty($checkOrder)) {
                    Log::error('Duplicate BTCPay webhook: '.$payload['webhookId']);

                    return response('Not Found', 404);
                } else {
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
            } else {
                Log::error('User not found for BTCPay webhook: '.$payload['metadata']['buyerEmail']);

                return response('Not Found', 404);
            }
        } elseif ($payload['type'] === 'InvoiceSettled') {
            // Check if we have the invoice_id in payments table and if we do, update the user account
            $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', 'Settled')->first();
            if (! empty($checkOrder)) {
                $user = User::query()->where('email', '=', $checkOrder->email)->first();
                if ($user) {
                    preg_match('/(?P<role>\w+(\s\+\+)?)[\s](?P<addYears>\d+)/i', $checkOrder->item_description, $matches);
                    User::updateUserRole($user->id, $matches['role']);
                    User::updateUserRoleChangeDate($user->id, null, $matches['addYears']);
                    Log::info('User: '.$user->username.' upgraded to '.$matches['role'].' for BTCPay webhook: '.$checkOrder->webhook_id);

                    return response('OK', 200);
                } else {
                    Log::error('User not found for BTCPay webhook: '.$checkOrder->webhook_id);

                    return response('Not Found', 404);
                }
            }
        }

        return response('OK', 200);
    }
}
