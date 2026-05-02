<?php
// تنظیمات دیتابیس خود را اینجا وارد کنید و سپس فایل را در مرورگر اجرا کنید
$host = 'localhost';
$dbname = 'YOUR_DB_NAME';
$user = 'YOUR_DB_USER';
$pass = 'YOUR_DB_PASS';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$dbname` ");

    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `chat_id` bigint PRIMARY KEY,
            `phone` varchar(20),
            `balance` bigint DEFAULT 0,
            `step` varchar(50),
            `has_trial` tinyint(1) DEFAULT 0,
            `is_admin` tinyint(1) DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS `settings` (
            `id` int PRIMARY KEY,
            `key_name` varchar(50),
            `val` text
        )",
        "CREATE TABLE IF NOT EXISTS `services` (
            `id` int AUTO_INCREMENT PRIMARY KEY,
            `user_id` bigint,
            `email` varchar(100),
            `uuid` varchar(100),
            `expiry_date` datetime
        )"
    ];

    foreach ($queries as $q) $pdo->exec($q);
    echo "✅ نصب با موفقیت انجام شد. این فایل را حذف کنید.";
} catch (PDOException $e) { echo "❌ خطا: " . $e->getMessage(); }