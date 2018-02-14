<?php

use App\Models\User;
use Blacklight\libraries\Geary;

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
        User::updateUserRoleChangeDate($callback_data['user_id'], \Carbon\Carbon::now()->addYears($addYear));
    }
}
