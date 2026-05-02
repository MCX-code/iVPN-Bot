<?php
require_once 'functions.php';

function createPaymentLink($gateway, $amount, $chat_id, $order_id) {
    $callback = "https://yourdomain.com/verify.php?order_id=$order_id&gateway=$gateway";

    if ($gateway == "aqayepardakht") {
        $pin = getSetting('aqayepardakht_pin');
        $ch = curl_init('https://panel.aqayepardakht.ir/api/v2/create');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'pin' => $pin,
            'amount' => $amount,
            'callback' => $callback,
            'chat_id' => $chat_id
        ]);
        $res = json_decode(curl_exec($ch), true);
        return ($res['status'] == 'success') ? "https://panel.aqayepardakht.ir/start/" . $res['transid'] : false;

    } elseif ($gateway == "nowpayments") {
        $api_key = getSetting('nowpayments_key');
        $ch = curl_init('https://api.nowpayments.io/v1/payment');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-api-key: $api_key", "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'price_amount' => $amount,
            'price_currency' => 'usd',
            'pay_currency' => 'usdttrc20',
            'order_id' => $order_id,
            'callback_url' => $callback
        ]));
        $res = json_decode(curl_exec($ch), true);
        return $res['invoice_url'] ?? false;
    }
    return false;
}