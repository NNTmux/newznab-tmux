<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminDashboardSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class BtcPaymentController extends BasePageController
{
    /**
     * BtcPay callback.
     */
    public function btcPayCallback(Request $request): Response
    {
        $hashCheck = 'sha256='.hash_hmac('sha256', $request->getContent(), config('nntmux.btcpay_webhook_secret'));
        if (! hash_equals($hashCheck, (string) $request->header('btcpay-sig'))) {
            Log::channel('btc_payment')->error('BTCPay webhook hash check failed: '.$request->header('btcpay-sig'));

            return response('Not Found', 404);
        }
        $payload = json_decode($request->getContent(), true);
        // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
        if ($payload['type'] === 'InvoicePaymentSettled') {
            $user = User::query()->where('email', '=', $payload['metadata']['buyerEmail'])->first();
            if ($user) {
                $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', Payment::PAYMENT_STATUS_SETTLED)->first();
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

                Cache::forget(AdminDashboardSnapshotService::CACHE_KEY);

                return response('OK', 200);
            }

            Log::channel('btc_payment')->error('User not found for BTCPay webhook: '.$payload['metadata']['buyerEmail']);

            return response('Not Found', 404);
        }

        if ($payload['type'] === 'InvoiceSettled') {
            // Check if we have the invoice_id in payments table and if we do, update the user account
            $checkOrder = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->where('payment_status', '=', Payment::PAYMENT_STATUS_SETTLED)->where(function ($query) {
                return $query->where('invoice_status', Payment::INVOICE_STATUS_PENDING)->orWhereNull('invoice_status');
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

                    if (! $this->isPurchasableRole($roleName)) {
                        Log::channel('btc_payment')->error('BTCPay webhook asked for a role that cannot be bought: '.$roleName.' (webhook: '.$checkOrder->webhook_id.')');

                        return response('Not Found', 404);
                    }

                    User::updateUserRole($user->id, $roleName, addYears: $addYears);
                    $checkOrder->update(['invoice_status' => Payment::INVOICE_STATUS_SETTLED]);
                    Log::channel('btc_payment')->info('User: '.$user->username.' upgraded to '.$roleName.' (+'.$addYears.' years) for BTCPay webhook: '.$checkOrder->webhook_id);

                    return response('OK', 200);
                }

                Log::channel('btc_payment')->error('User not found for BTCPay webhook: '.$checkOrder->webhook_id);

                return response('Not Found', 404);
            }

            $existingPayment = Payment::query()->where('invoice_id', '=', $payload['invoiceId'])->first();
            if ($existingPayment === null) {
                Log::channel('btc_payment')->error('InvoiceSettled webhook received but no payment recorded for invoice: '.$payload['invoiceId'].' (webhook: '.($payload['webhookId'] ?? 'unknown').', buyer: '.($payload['metadata']['buyerEmail'] ?? 'unknown').')');

                return response('Not Found', 404);
            }

            Log::channel('btc_payment')->info('Duplicate InvoiceSettled webhook for already settled invoice: '.$payload['invoiceId']);
        }

        return response('OK', 200);
    }

    /**
     * Only an existing role without staff or disabled status can be bought.
     */
    private function isPurchasableRole(string $roleName): bool
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
        if ($role === null) {
            return false;
        }

        foreach ([UserRole::ADMIN, UserRole::MODERATOR, UserRole::DISABLED] as $blockedRole) {
            if (strcasecmp((string) $role->name, $blockedRole->label()) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Payment.status values from BTCPay Greenfield API (payload.payment.status). Used for admin filters.
     *
     * @return list<string>
     */
    public static function paymentStatusesForAdminFilter(): array
    {
        return [
            'Pending',
            'Processing',
            Payment::PAYMENT_STATUS_SETTLED,
            'Invalid',
            'Manual',
        ];
    }

    /**
     * invoice_status values used in our DB (migration default Pending; webhook sets Settled after role upgrade).
     *
     * @return list<string>
     */
    public static function invoiceStatusesForAdminFilter(): array
    {
        return [
            Payment::INVOICE_STATUS_PENDING,
            Payment::INVOICE_STATUS_SETTLED,
        ];
    }
}
