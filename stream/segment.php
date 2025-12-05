<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

$u  = $_GET['u'] ?? '';
$p  = $_GET['p'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$exp   = (int)($_GET['exp'] ?? 0);
$token = $_GET['token'] ?? '';

$url = $_GET['url'] ?? '';

if ($u==='' || $id<1 || $url==='') {
  http_response_code(400); exit("Bad params");
}

$pdo = db();

/* user */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$u]);
$user = $st->fetch();
if (!$user) { http_response_code(401); exit("Invalid user"); }

/* token OR password */
$token_ok = ($token && $exp && verify_token($u, $id, $exp, $token));
$pass_ok  = ($p !== '' && password_verify($p, $user['password_hash']));
if (!$token_ok && !$pass_ok) {
  http_response_code(401); exit("Invalid credentials");
}


/* adult filter */
$st = $pdo->prepare(\"SELECT IFNULL(is_adult,0) AS is_adult FROM channels WHERE id=?\");
$st->execute([$id]);
$ch = $st->fetch();
if ($ch && empty($user['allow_adult']) && (int)$ch['is_adult']===1) {
  http_response_code(403); exit(\"Adult content not allowed\");
}

/* sub */
$st = $pdo->prepare("
  SELECT s.*
  FROM subscriptions s
  WHERE s.user_id=? AND s.status='active' AND s.ends_at>NOW()
  ORDER BY s.ends_at DESC LIMIT 1
");
$st->execute([$user['id']]);
$sub = $st->fetch();
if (!$sub) { http_response_code(403); exit("No active subscription"); }

/* let upstream dictate content-type */
header_remove("Content-Type");

/* ---------- stream segment bytes (with compatibility headers) ---------- */
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_RETURNTRANSFER => false, // stream directly to client
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => false,
  CURLOPT_ENCODING => "", // accept gzip/deflate
  CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
  CURLOPT_HTTPHEADER => [
    'Accept: */*',
    'Connection: keep-alive'
  ],
  CURLOPT_TIMEOUT => 20
]);
curl_exec($ch);
curl_close($ch);
