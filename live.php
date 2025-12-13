<?php
// live.php - router for Xtream-style /live/... links
require_once __DIR__ . '/helpers.php';

// Path: /live/{u}/{pass_or_token}/{id}.{ext}
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$parts = array_values(array_filter(explode('/', $uri)));

if (count($parts) < 4 || strtolower($parts[0]) !== 'live') {
  http_response_code(404);
  exit('Not Found');
}

$u = $parts[1];
$mid = $parts[2];
$idPart = $parts[3];
$id = (int)preg_replace('/\..*$/', '', $idPart);

$exp = (int)($_GET['exp'] ?? 0);
$token = $_GET['token'] ?? '';

// Token-only mode: /live/u/{token}/{id}.m3u8?exp=...
if (preg_match('/^[a-f0-9]{64}$/i', $mid)) {
  $token = $mid;
  $_GET['p'] = '';
} else {
  // Legacy: /live/u/{password}/{id}.m3u8?exp=...&token=...
  $_GET['p'] = $mid;
}

$_GET['u'] = $u;
$_GET['id'] = $id;
$_GET['exp'] = $exp;
$_GET['token'] = $token;

require __DIR__ . '/stream/index.php';
