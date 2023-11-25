<?php

namespace App\Http\Controllers;

use App\Models\User;
use Gearer;
use Illuminate\Http\Request;
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
                $callback_data = $data;

                $order = Gearer::createOrder($price, $keychain_id, $callback_data);

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
        $order = Gearer::handleOrderStatusCallback();

        // Order status was received
        if ($order !== false) {
            $newRole = $order['role'];
            $amount = $order['price'];
            $addYear = $order['addyears'];
            // If order was paid in full (2) or overpaid (4)
            if ((int) $order['status'] === 2 || (int) $order['status'] === 4) {
                User::updateUserRole($order['user_id'], $newRole);
                User::updateUserRoleChangeDate($order['user_id'], null, $addYear);
            }
        }
    }
}
