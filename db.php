<?php
// db.php
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/migration.php';

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

  // Best-effort runtime migrations (safe to call multiple times).
  try {
    if (function_exists('db_migrate')) db_migrate($pdo);
  } catch (Throwable $e) {
    // Don't break the app if the hosting DB user can't ALTER.
  }

  return $pdo;
}
