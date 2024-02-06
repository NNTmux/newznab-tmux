<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Blacklight\libraries\Geary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class BtcPaymentController extends BasePageController
{
    /**
     * @return \Illuminate\Http\RedirectResponse|void
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPreferences();
        $gateway_id = config('settings.mycelium_gateway_id');
        $gateway_secret = config('settings.mycelium_gateway_secret');

        $action = $request->input('action') ?? 'view';
        $donation = Role::query()->where('donation', '>', 0)->get(['id', 'name', 'donation', 'addyears']);
        $this->smarty->assign('donation', $donation);

        switch ($action) {
            case 'submit':
                $price = $request->input('price');
                $role = $request->input('role');
                $roleName = $request->input('rolename');
                $addYears = $request->input('addyears');
                $data = ['user_id' => $this->userdata->id, 'username' => $this->userdata->username, 'price' => $price, 'role' => $role, 'rolename' => $roleName, 'addyears' => $addYears];
                $keychain_id = random_int(0, 19);
                $callback_data = json_encode($data);

                $geary = new Geary($gateway_id, $gateway_secret);
                $order = $geary->create_order($price, $keychain_id, $callback_data);

                if ($order->payment_id) {
                    // Redirect to a payment gateway
                    $url = 'https://gateway.gear.mycelium.com/pay/'.$order->payment_id;

                    return redirect()->to($url);
                }
                break;
            case 'view':
            default:
                $userId = $this->userdata->id;
                break;
        }

        $title = 'Become a supporter';
        $meta_title = 'Become a supporter';
        $meta_description = 'Become a supporter';

        $content = $this->smarty->fetch('btc_payment.tpl');

        $this->smarty->assign(compact('content', 'meta_title', 'title', 'meta_description'));
        $this->pagerender();
    }

    /**
     * Callback data from Mycelium Gear.
     */
    public function callback(): void
    {
        $gateway_id = config('settings.mycelium_gateway_id');
        $gateway_secret = config('settings.mycelium_gateway_secret');

        $geary = new Geary($gateway_id, $gateway_secret);
        $order = $geary->check_order_callback();

        // Order status was received
        if ($order !== false) {
            $callback_data = json_decode($order['callback_data'], true);
            $newRole = $callback_data['role'];
            $amount = $callback_data['price'];
            $addYear = $callback_data['addyears'];
            // If order was paid in full (2) or overpaid (4)
            if ((int) $order['status'] === 2 || (int) $order['status'] === 4) {
                User::updateUserRole($callback_data['user_id'], $newRole);
                User::updateUserRoleChangeDate($callback_data['user_id'], null, $addYear);
            }
        }
    }

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
                        'payment_method' => $payload['paymentMethod'],
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
