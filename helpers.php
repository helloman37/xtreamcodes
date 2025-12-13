<?php
// helpers.php

function e($str) {
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function flash_set($msg, $type='info') {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION['flash'] = ['msg'=>$msg, 'type'=>$type];
}

function flash_show() {
  if (!empty($_SESSION['flash'])) {
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo "<div style='padding:8px;border:1px solid #333;margin:10px 0;background:#0d0f14;color:#e6f1ff'>
      <b>".e($f['type']).":</b> ".e($f['msg'])."
    </div>";
  }
}

// --- CSRF helpers (admin forms) ---
function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_input(): string {
  return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_validate(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $sent = $_POST['csrf_token'] ?? '';
  $good = $_SESSION['csrf_token'] ?? '';
  if (!$sent || !$good || !hash_equals($good, $sent)) {
    http_response_code(403);
    die('Forbidden (CSRF)');
  }
}
// --- end CSRF helpers ---

function parse_m3u(string $content): array {
  $lines = preg_split("/\r\n|\n|\r/", $content);
  $channels = [];
  $current = null;

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#EXTM3U')) continue;

    if (str_starts_with($line, '#EXTINF')) {
      $current = [
        'name' => '',
        'group_title' => null,
        'tvg_id' => null,
        'tvg_name' => null,
        'tvg_logo' => null,
        'stream_url' => null,
        'epg_url' => null
      ];

      preg_match_all('/([a-zA-Z0-9\-\_]+)="([^"]*)"/', $line, $matches, PREG_SET_ORDER);
      foreach ($matches as $m) {
        $key = strtolower($m[1]);
        $val = $m[2];
        if ($key === 'group-title') $current['group_title'] = $val;
        if ($key === 'tvg-id') $current['tvg_id'] = $val;
        if ($key === 'tvg-name') $current['tvg_name'] = $val;
        if ($key === 'tvg-logo') $current['tvg_logo'] = $val;
      }

      $parts = explode(',', $line, 2);
      $current['name'] = trim($parts[1] ?? 'Unknown');
    } else if ($current && !str_starts_with($line, '#')) {
      $current['stream_url'] = $line;
      $channels[] = $current;
      $current = null;
    }
  }

  return $channels;
}

function check_stream_url(string $url, int $timeout=8): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => $timeout,
    CURLOPT_USERAGENT => 'IPTV-Admin-Checker/1.0'
  ]);
  curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  $works = ($code >= 200 && $code < 400);
  return ['code'=>$code, 'works'=>$works, 'error'=>$err];
}

/* ---------- SECURITY + LOGGING HELPERS ---------- */

function get_client_ip(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function strict_device_id_enabled(): bool {
  try {
    $config = require __DIR__ . '/config.php';
    return !empty($config['strict_device_id']);
  } catch (Throwable $e) {
    return false;
  }
}

function get_device_fingerprint(): string {
  // Prefer explicit device id (apps can send ?device_id= or X-Device-ID)
  $dev = trim($_GET['device_id'] ?? ($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));

  if ($dev !== '') {
    return substr(hash('sha256', $dev), 0, 32);
  }

  // If strict mode is on, do NOT fall back to UA (forces real device_id from your app)
  if (strict_device_id_enabled()) {
    return '';
  }

  $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
  return substr(hash('sha256', $ua.'|'.$lang), 0, 32);
}


function rate_limit(string $key, int $limit, int $window_seconds): bool {
  // Returns true if allowed, false if rate-limited.
  $dir = __DIR__ . '/tmp/ratelimit';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);

  $f = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key) . '.json';
  $now = time();

  $data = ['t' => $now, 'hits' => []];
  if (is_file($f)) {
    $raw = @file_get_contents($f);
    $j = $raw ? json_decode($raw, true) : null;
    if (is_array($j)) $data = $j;
  }

  // prune
  $hits = array_values(array_filter($data['hits'] ?? [], fn($t) => ($t >= $now - $window_seconds)));
  if (count($hits) >= $limit) {
    $data['hits'] = $hits;
    @file_put_contents($f, json_encode($data));
    return false;
  }
  $hits[] = $now;
  $data['hits'] = $hits;
  @file_put_contents($f, json_encode($data));
  return true;
}

function parse_cidr_list(?string $txt): array {
  if (!$txt) return [];
  $parts = preg_split('/[\s,;]+/', trim($txt));
  return array_values(array_filter(array_map('trim', $parts)));
}

function ip_in_cidr(string $ip, string $cidr): bool {
  if (strpos($cidr, '/') === false) {
    return $ip === $cidr;
  }
  [$subnet, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ip_long = ip2long($ip);
    $sub_long = ip2long($subnet);
    $mask_long = -1 << (32 - $mask);
    return ($ip_long & $mask_long) === ($sub_long & $mask_long);
  }
  // IPv6 CIDR not supported in this minimal helper
  return false;
}

function ip_allowed(string $ip, ?string $allowlist, ?string $denylist): bool {
  $deny = parse_cidr_list($denylist);
  foreach ($deny as $cidr) {
    if (ip_in_cidr($ip, $cidr)) return false;
  }
  $allow = parse_cidr_list($allowlist);
  if (!$allow) return true;
  foreach ($allow as $cidr) {
    if (ip_in_cidr($ip, $cidr)) return true;
  }
  return false;
}

function audit_log(string $event, ?int $user_id=null, array $meta=[], ?int $reseller_id=null): void {
  try {
    $pdo = db();
    $pdo->prepare('INSERT INTO audit_logs (user_id,reseller_id,ip,event,meta_json) VALUES (?,?,?,?,?)')
        ->execute([$user_id, $reseller_id, get_client_ip(), $event, $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null]);
  } catch (Throwable $e) {
    // ignore
  }

  // Optional webhook
  try {
    $config = require __DIR__ . '/config.php';
    $hook = $config['webhook_url'] ?? '';
    if ($hook) {
      $payload = json_encode([
        'event' => $event,
        'user_id' => $user_id,
        'reseller_id' => $reseller_id,
        'ip' => get_client_ip(),
        'meta' => $meta,
        'ts' => time()
      ], JSON_UNESCAPED_SLASHES);
      $ch = curl_init($hook);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3
      ]);
      @curl_exec($ch);
      @curl_close($ch);
    }
  } catch (Throwable $e) {
    // ignore
  }
}

/* ---------- TOKENS ---------- */
function random_hex_token(int $bytes=16): string {
  return bin2hex(random_bytes($bytes));
}


function make_token(string $username, int $item_id, int $exp, string $type='live'): string {
  $config = require __DIR__ . '/config.php';
  $data = $type . '|' . $username . '|' . $item_id . '|' . $exp;
  return hash_hmac('sha256', $data, $config['secret_key']);
}

function verify_token(string $username, int $item_id, int $exp, string $token, string $type='live'): bool {
  if ($exp < time()) return false;

  // New typed token
  $good = make_token($username, $item_id, $exp, $type);
  if (hash_equals($good, $token)) return true;

  // Backward compatibility: old tokens were username|id|exp without type
  $config = require __DIR__ . '/config.php';
  $legacy_data = $username . '|' . $item_id . '|' . $exp;
  $legacy = hash_hmac('sha256', $legacy_data, $config['secret_key']);
  return hash_equals($legacy, $token);
}

// -------------------- Reseller auth (v10+) --------------------
if (!function_exists('reseller_login')) {
  function reseller_login($username, $password) {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM resellers WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username]);
    $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reseller) return false;
    if (!password_verify($password, $reseller['password_hash'])) return false;

    $_SESSION['reseller_id'] = $reseller['id'];
    $_SESSION['reseller_username'] = $reseller['username'];
    return true;
  }
}

if (!function_exists('reseller_auth')) {
  function reseller_auth() {
    return isset($_SESSION['reseller_id']);
  }
}

