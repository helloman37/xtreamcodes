<?php
// Basic configuration for IPTV panel

define('DB_HOST', 'localhost');
define('DB_NAME', 'iptv_panel');
define('DB_USER', 'root');
define('DB_PASS', 'password');

// Create PDO connection (PHP 7.4 compatible)
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = [
    'admin_session_key' => 'iptv_admin_logged_in',
    'admin_username_key' => 'iptv_admin_username',
];

function is_admin_logged_in(): bool
{
    global $config;
    return !empty($_SESSION[$config['admin_session_key']]);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function h(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
