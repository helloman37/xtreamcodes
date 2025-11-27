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

/* ---------- TOKENS ---------- */

function make_token(string $username, int $channel_id, int $exp): string {
  $config = require __DIR__ . '/config.php';
  $data = $username . '|' . $channel_id . '|' . $exp;
  return hash_hmac('sha256', $data, $config['secret_key']);
}

function verify_token(string $username, int $channel_id, int $exp, string $token): bool {
  if ($exp < time()) return false;
  $good = make_token($username, $channel_id, $exp);
  return hash_equals($good, $token);
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

