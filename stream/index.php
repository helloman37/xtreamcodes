<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

$config   = require __DIR__ . '/../config.php';
$base_url = rtrim($config['base_url'], '/');
$dev_win  = (int)($config['device_window'] ?? 120);
$max_ip_changes = (int)($config['max_ip_changes'] ?? 3);
$max_ip_window  = (int)($config['max_ip_window'] ?? 600);

header("Access-Control-Allow-Origin: *");

$u  = $_GET['u'] ?? '';
$p  = $_GET['p'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$exp   = (int)($_GET['exp'] ?? 0);
$token = $_GET['token'] ?? '';

if ($u === '' || $id < 1) {
  http_response_code(400);
  exit("Bad params");
}

$pdo = db();

/* user */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$u]);
$user = $st->fetch();
if (!$user) { http_response_code(401); exit("Invalid user"); }

/* allow token OR password */
$token_ok = ($token && $exp && verify_token($u, $id, $exp, $token));
$pass_ok  = ($p !== '' && password_verify($p, $user['password_hash']));
if (!$token_ok && !$pass_ok) {
  http_response_code(401);
  exit("Invalid credentials");
}

/* sub + plan */
$st = $pdo->prepare("
  SELECT s.*, p.max_streams
  FROM subscriptions s
  JOIN plans p ON p.id=s.plan_id
  WHERE s.user_id=? AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())
  ORDER BY s.ends_at DESC LIMIT 1
");
$st->execute([$user['id']]);
$sub = $st->fetch();
if (!$sub) { http_response_code(403); exit("No active subscription"); }
$max_streams = (int)$sub['max_streams'];

/* session tracking */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 250);

$pdo->prepare("
  INSERT INTO stream_sessions (user_id, channel_id, ip, user_agent, last_seen)
  VALUES (?,?,?,?, NOW())
")->execute([$user['id'], $id, $ip, $ua]);

/* device limit */
$st = $pdo->prepare("
  SELECT COUNT(*) AS c FROM (
    SELECT DISTINCT ip, user_agent
    FROM stream_sessions
    WHERE user_id=? 
      AND last_seen > (NOW() - INTERVAL ? SECOND)
  ) x
");
$st->execute([$user['id'], $dev_win]);
$active_devices = (int)$st->fetch()['c'];

if ($active_devices > $max_streams) {
  http_response_code(429);
  exit("Device limit reached");
}

/* anti-restream: too many IP swaps */
$st = $pdo->prepare("
  SELECT COUNT(DISTINCT ip) AS c
  FROM stream_sessions
  WHERE user_id=? 
    AND last_seen > (NOW() - INTERVAL ? SECOND)
");
$st->execute([$user['id'], $max_ip_window]);
$ip_count = (int)$st->fetch()['c'];

if ($ip_count > $max_ip_changes) {
  http_response_code(403);
  exit("Restream detected");
}


/* ---------- upstream request headers ---------- */
$client_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
$up_headers = [
  'Accept: */*',
  'Connection: keep-alive'
];
// forward browser/app headers if present (many CDNs require these)
if (!empty($_SERVER['HTTP_REFERER'])) $up_headers[] = 'Referer: '.$_SERVER['HTTP_REFERER'];
if (!empty($_SERVER['HTTP_ORIGIN']))  $up_headers[] = 'Origin: '.$_SERVER['HTTP_ORIGIN'];
if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $up_headers[] = 'Accept-Language: '.$_SERVER['HTTP_ACCEPT_LANGUAGE'];

/* channel source */
$st = $pdo->prepare("SELECT stream_url, direct_play, IFNULL(is_adult,0) AS is_adult FROM channels WHERE id=?");
$st->execute([$id]);
$channel = $st->fetch();
if (!$channel) { http_response_code(404); exit("Channel not found"); }
if (empty($user["allow_adult"]) && (int)$channel["is_adult"] === 1) {
  http_response_code(403); exit("Adult content not allowed");
}

$source = $channel['stream_url'];

// direct play bypass (for stubborn legal FAST feeds)
if (!empty($channel['direct_play'])) {
  header("Location: ".$source, true, 302);
  exit;
}


/* if not HLS, redirect */
/* if not HLS, proxy bytes to keep URL hidden */
if (!preg_match('/\.m3u8(\?|$)/i', $source)) {
  $ch = curl_init($source);
  curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $client_ua,
    CURLOPT_HTTPHEADER => $up_headers,
    CURLOPT_HEADERFUNCTION => function($curl, $header) {
      $len = strlen($header);
      if (stripos($header, 'Content-Type:') === 0) header(trim($header));
      if (stripos($header, 'Content-Length:') === 0) header(trim($header));
      return $len;
    }
  ]);
  curl_exec($ch);
  curl_close($ch);
  exit;
}

/* ---------- fetch upstream playlist (with compatibility headers) ---------- */
$ch = curl_init($source);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => false,
  CURLOPT_ENCODING => "", // accept gzip/deflate
  CURLOPT_USERAGENT => $client_ua,
  CURLOPT_HTTPHEADER => $up_headers,
  CURLOPT_TIMEOUT => 20
]);
$playlist  = curl_exec($ch);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($playlist === false || trim($playlist) === '') {
  // Upstream blocked proxy fetch (often IP/geo or header gated).
  // Fallback to direct redirect so playback still works,
  // while the playlist continues to show YOUR URL.
  header("Location: ".$source, true, 302);
  exit;
}

/* base for relative URLs */
$base_dir = preg_replace('~/[^/]*$~', '/', $final_url);
$lines = preg_split("/\\r\\n|\\n|\\r/", $playlist);

header("Content-Type: application/vnd.apple.mpegurl; charset=utf-8");

$out = [];
foreach ($lines as $line) {
  $trim = trim($line);

  if ($trim === '' || str_starts_with($trim, '#')) {
    $out[] = $line;
    continue;
  }

  // absolute upstream URL
  if (!preg_match('~^https?://~i', $trim)) {
    $trim = $base_dir . ltrim($trim, '/');
  }

  // rewrite through YOUR domain segment proxy (no leak)
  $seg = $base_url."/seg/".rawurlencode($u)."/".rawurlencode($p ?: '')."/".$id
       ."?url=".rawurlencode($trim);

  if ($token_ok) {
    $seg .= "&exp=".$exp."&token=".$token;
  }

  $out[] = $seg;
}

echo implode("\\n", $out);
