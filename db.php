<?php
// db.php
$config = require __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  global $config;

  if ($pdo) return $pdo;

  $db = $config['db'] ?? [];
  $host = $db['host'] ?? 'localhost';
  $name = $db['name'] ?? '';
  $user = $db['user'] ?? '';
  $pass = $db['pass'] ?? '';
  $charset = $db['charset'] ?? 'utf8mb4';

  if (!$name || !$user) {
    throw new RuntimeException("DB config missing name/user. Check config.php.");
  }

  $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  return $pdo;
}
