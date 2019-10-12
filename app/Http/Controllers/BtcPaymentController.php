<?php

namespace App\Http\Controllers;

use App\Models\User;
use Omnipay\Omnipay;
use App\Models\Settings;
use Illuminate\Http\Request;
use App\Models\PaypalPayment;
use Blacklight\libraries\Geary;
use Spatie\Permission\Models\Role;

class BtcPaymentController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPrefs();
        $gateway_id = env('MYCELIUM_GATEWAY_ID');
        $gateway_secret = env('MYCELIUM_GATEWAY_SECRET');

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

                    return redirect($url);
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
    public function callback()
    {
        $gateway_id = env('MYCELIUM_GATEWAY_ID');
        $gateway_secret = env('MYCELIUM_GATEWAY_SECRET');

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
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function paypal(Request $request)
    {
        $this->setPrefs();
        $gateway = Omnipay::create('PayPal_Rest');
        $gateway->initialize(['clientId' => env('PAYPAL_CLIENTID'), 'secret' => env('PAYPAL_SECRET'), 'testMode' => true]);
        $amount = $request->input('amount');

        // Do a purchase transaction on the gateway
        try {
            $transaction = $gateway->purchase([
                'amount' => $amount,
                'currency' => 'USD',
                'description' => $this->userdata->id,
                'returnUrl' => 'http://homestead.test/thankyou?id='.$this->userdata->id.'&amount='.$amount,
                'cancelUrl' => 'http://homestead.test/payment_failed',
            ]);
            $response = $transaction->send();

            if ($response->isSuccessful()) {
                return redirect($response->getRedirectUrl());
            } elseif ($response->isRedirect()) {
                return $response->redirect();
            }
        } catch (\Exception $e) {
            echo "Exception caught while attempting authorize.\n";
            echo 'Exception type == '.get_class($e)."\n";
            echo 'Message == '.$e->getMessage()."\n";
        }
    }

    /**
     * @throws \Exception
     */
    public function showPaypal()
    {
        $this->setPrefs();
        $donation = Role::query()->where('donation', '>', 0)->get(['id', 'name', 'donation', 'addyears']);
        $this->smarty->assign('donation', $donation);
        $title = 'Become a supporter';
        $meta_title = 'Become a supporter';
        $meta_description = 'Become a supporter';
        $content = $this->smarty->fetch('pay_by_paypal.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'title', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function paypalCallback(Request $request)
    {
        $this->setPrefs();
        $amount = $request->input('amount');
        $userId = $request->input('id');

        $role = Role::query()->where('donation', $amount)->first();

        $gateway = Omnipay::create('PayPal_Rest');
        $gateway->initialize(['clientId' => env('PAYPAL_CLIENTID'), 'secret' => env('PAYPAL_SECRET'), 'testMode' => true]);

        $response = $gateway->completePurchase(
            [
                'amount' => $amount,
                'currency' => 'USD',
                'description' => $userId,
                'payerId' => $request->input('PayerID'),
                'transactionReference' => $request->input('paymentId'),
            ])->send();

        if ($response->isSuccessful()) {
            $check = PaypalPayment::query()->where('transaction_id', $request->input('paymentId'))->first();
            if ($check === null) {
                User::updateUserRole($userId, $role->id);
                User::updateUserRoleChangeDate($userId, null, $role->addyears);
                PaypalPayment::query()->insert(['users_id' => $userId, 'transaction_id' => $request->input('paymentId'), 'created_at' => now(), 'updated_at' => now()]);
                $title = 'Cheers!';
                $meta_title = Settings::settingValue('site.main.title').' - Payment Complete';
                $meta_description = 'Payment Complete';
                $content = $this->smarty->fetch('thankyou.tpl');
                $this->smarty->assign(compact('content', 'meta_title', 'title', 'meta_description'));

                $this->pagerender();
            } else {
                echo 'Transaction already exists!';
            }
        } else {
            return redirect('payment_failed');
        }
    }

    public function paypalFailed()
    {
        echo 'Shit happens';
    }
}
