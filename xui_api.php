<?php
require_once 'db.php';

class XuiManager {
    private $url;
    private $username;
    private $password;
    private $cookie;

    public function __construct() {
        // اطلاعات را از تابع getSetting که در functions.php است می‌گیرد
        $this->url = getSetting('xui_url');
        $this->username = getSetting('xui_user');
        $this->password = getSetting('xui_pass');
    }

    private function login() {
        $ch = curl_init($this->url . "/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['username' => $this->username, 'password' => $this->password]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $res = curl_exec($ch);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $res, $matches);
        $this->cookie = $matches[1][0] ?? null;
        return $this->cookie;
    }

    public function addClient($email, $days, $gb, $inbound_id) {
        if (!$this->login()) return false;

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $expiry = (time() + ($days * 24 * 60 * 60)) * 1000;
        
        $params = [
            "id" => $inbound_id,
            "settings" => json_encode(["clients" => [[
                "id" => $uuid, "email" => $email, "totalGB" => $gb * 1024 * 1024 * 1024, "expiryTime" => $expiry, "enable" => true
            ]]])
        ];

        $ch = curl_init($this->url . "/panel/api/inbounds/addClient");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: " . $this->cookie]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true);
        
        return ($res['success']) ? $uuid : false;
    }
}