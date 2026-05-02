<?php
require_once 'functions.php';
require_once 'payment_handler.php';
require_once 'db.php';

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['from']['id'];
$text = $update['message']['text'] ?? null;
$data = $update['callback_query']['data'] ?? null;
$photo = $update['message']['photo'] ?? null;

// ۱. تنظیمات ادمین (جایگزین کردن ID خودتان الزامی است)
$admin_id = getSetting('admin_id'); 

// ۲. پردازش دکمه‌های شیشه‌ای (Inline Buttons)
if ($data) {
    if ($data == "buy") {
        $plans = [
            ['text' => "پلن ۱ ماهه (۲۰ گیگ) - ۵۰,۰۰۰ تومان", 'callback_data' => "plan_1_50000"],
            ['text' => "پلن ۳ ماهه (۵۰ گیگ) - ۱۲۰,۰۰۰ تومان", 'callback_data' => "plan_2_120000"]
        ];
        $key = ['inline_keyboard' => array_chunk($plans, 1)];
        sendMessage($chat_id, "لطفاً یکی از پلن‌های زیر را انتخاب کنید:", $key);
    }

    // انتخاب پلن و نمایش روش پرداخت
    if (strpos($data, "plan_") !== false) {
        $ex = explode("_", $data);
        $plan_id = $ex[1];
        $price = $ex[2];
        
        $key = ['inline_keyboard' => [
            [['text' => "💳 کارت به کارت", 'callback_data' => "pay_card_$price_$plan_id"]],
            [['text' => "🌐 درگاه مستقیم (آقای پرداخت)", 'callback_data' => "pay_online_$price_$plan_id"]]
        ]];
        sendMessage($chat_id, "مبلغ قابل پرداخت: " . number_format($price) . " تومان\nروش پرداخت را انتخاب کنید:", $key);
    }

    // اگر کاربر "کارت به کارت" را انتخاب کرد
    if (strpos($data, "pay_card_") !== false) {
        $ex = explode("_", $data);
        $price = $ex[2];
        $plan_id = $ex[3];

        // ذخیره سفارش در حالت Pending در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, plan_id, amount, method, status) VALUES (?, ?, ?, 'card', 'pending')");
        $stmt->execute([$chat_id, $plan_id, $price]);
        $order_id = $pdo->lastInsertId();

        // تغییر وضعیت کاربر برای دریافت رسید
        $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?")->execute(["wait_receipt_$order_id", $chat_id]);

        $card_number = getSetting('card_number') ?: "0000-0000-0000-0000";
        $msg = "لطفاً مبلغ " . number_format($price) . " تومان را به شماره کارت زیر واریز کنید:\n\n`$card_number`\n\n**پس از واریز، حتماً عکس رسید را در همینجا ارسال کنید.**";
        sendMessage($chat_id, $msg);
    }
}

// ۳. پردازش پیام‌های متنی و عکس‌ها
if ($text == "/start") {
    $pdo->prepare("INSERT IGNORE INTO users (chat_id, step) VALUES (?, 'none')")->execute([$chat_id]);
    $key = ['inline_keyboard' => [
        [['text' => "🛒 خرید VPN", 'callback_data' => "buy"]],
        [['text' => "🎁 تست رایگان", 'callback_data' => "trial"], ['text' => "👤 حساب کاربری", 'callback_data' => "profile"]]
    ]];
    sendMessage($chat_id, "سلام! به ربات فروش VPN خوش آمدید.\nیکی از گزینه‌های زیر را انتخاب کنید:", $key);
}

// دریافت عکس رسید بانکی
if ($photo) {
    $stmt = $pdo->prepare("SELECT step FROM users WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $user_step = $stmt->fetchColumn();

    if (strpos($user_step, "wait_receipt_") !== false) {
        $order_id = str_replace("wait_receipt_", "", $user_step);
        $file_id = end($photo)['file_id'];

        // ۱. ارسال عکس برای ادمین جهت تایید
        $admin_msg = "🔔 رسید جدید دریافت شد!\nکد سفارش: $order_id\nآیدی کاربر: $chat_id\n\nبرای تایید و ساخت خودکار اکانت از پنل ادمین اقدام کنید یا دستور زیر را بزنید:\n/confirm_$order_id";
        
        // ارسال عکس به ادمین (با استفاده از متد sendPhoto تلگرام)
        $token = getSetting('bot_token');
        file_get_contents("https://api.telegram.org/bot$token/sendPhoto?chat_id=$admin_id&photo=$file_id&caption=" . urlencode($admin_msg));

        // ۲. اطلاع به کاربر
        sendMessage($chat_id, "✅ رسید شما دریافت شد و برای مدیریت ارسال گردید.\nپس از تایید ادمین، اطلاعات اکانت به صورت خودکار برای شما ارسال می‌شود.");
        
        // ۳. ریست کردن وضعیت کاربر
        $pdo->prepare("UPDATE users SET step = 'none' WHERE chat_id = ?")->execute([$chat_id]);
    }
}

// ۴. بخش مدیریت ادمین (تایید دستی رسید)
if ($chat_id == $admin_id && strpos($text, "/confirm_") !== false) {
    $order_id = str_replace("/confirm_", "", $text);
    
    // اجرای فایل verify.php به صورت داخلی برای ساخت اکانت
    // یا می‌توانید کد ساخت اکانت را مستقیماً اینجا صدا بزنید:
    require_once 'xui_api.php';
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'pending'");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $xui = new XuiManager();
        $email = "User_" . $order['user_id'] . "_" . rand(100, 999);
        $uuid = $xui->addClient($email, 30, 20, getSetting('inbound_id')); // فرض: ۳۰ روز ۲۰ گیگ

        if ($uuid) {
            $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$order_id]);
            $domain = getSetting('domain');
            $vless = "vless://$uuid@$domain:443?security=tls&encryption=none#$email";
            
            sendMessage($order['user_id'], "🎉 رسید شما تایید شد!\nاکانت شما ساخته شد:\n\n<code>$vless</code>");
            sendMessage($admin_id, "✅ سفارش $order_id تایید و تحویل داده شد.");
        } else {
            sendMessage($admin_id, "❌ خطا در اتصال به پنل X-UI برای سفارش $order_id");
        }
    }
}