<?php
require_once 'db.php';

function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT val FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $token = getSetting('bot_token');
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $post_fields['reply_markup'] = json_encode($keyboard);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($ch);
}

// تابع لاگین و ساخت اکانت در X-UI
function addVpnAccount($chat_id, $email, $days, $gb) {
    $panel_url = getSetting('xui_url');
    $user = getSetting('xui_user');
    $pass = getSetting('xui_pass');
    
    // ۱. Login to X-UI
    $ch = curl_init($panel_url . "/login");
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['username' => $user, 'password' => $pass]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $res = curl_exec($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $res, $matches);
    $cookie = $matches[1][0] ?? null;

    if (!$cookie) return false;

    // ۲. Add Client
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $expiry = (time() + ($days * 24 * 60 * 60)) * 1000;
    
    $data = [
        "id" => getSetting('inbound_id'),
        "settings" => json_encode(["clients" => [[
            "id" => $uuid, "email" => $email, "totalGB" => $gb * 1024 * 1024 * 1024, "expiryTime" => $expiry, "enable" => true
        ]]])
    ];

    $ch = curl_init($panel_url . "/panel/api/inbounds/addClient");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $cookie"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $final = json_decode(curl_exec($ch), true);

    if ($final['success']) {
        return "vless://$uuid@" . getSetting('domain') . ":443?security=tls&encryption=none#$email";
    }
    return false;
}