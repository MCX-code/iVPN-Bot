<?php
require_once 'xui_api.php';
require_once 'functions.php';

$order_id = $_GET['order_id'];
$gateway = $_GET['gateway'];
$status = $_GET['status']; // برای آقای پرداخت

if (($gateway == "aqayepardakht" && $status == "success") || ($gateway == "nowpayments")) {
    
    // ۱. استخراج اطلاعات سفارش از دیتابیس (فرض: در مرحله قبل ذخیره کردید)
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'pending'");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $xui = new XuiManager();
        $email = "User_" . $order['user_id'] . "_" . rand(100, 999);
        
        // ساخت اکانت در پنل
        $uuid = $xui->addClient($email, $order['days'], $order['gb'], getSetting('inbound_id'));

        if ($uuid) {
            // ۲. آپدیت وضعیت سفارش
            $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$order_id]);
            
            // ۳. ساخت لینک اتصال
            $domain = getSetting('domain');
            $final_link = "vless://$uuid@$domain:443?security=tls&encryption=none#$email";
            
            sendMessage($order['user_id'], "✅ پرداخت موفق! اکانت شما ساخته شد:\n\n<code>$final_link</code>");
            echo "<h1>پرداخت موفق! به تلگرام برگردید.</h1>";
        }
    }
} else {
    echo "<h1>پرداخت ناموفق بود.</h1>";
}