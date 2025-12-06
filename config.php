<?php
require_once 'db.php';
require_once 'helpers.php';

$config   = require __DIR__ . '/config.php';
$base_url = rtrim($config['base_url'], '/');

// Derive site root URL (base_url may include /public)
$site_url = rtrim($base_url, '/');
if (preg_match('~/public$~', $site_url)) {
  $site_url = preg_replace('~/public$~', '', $site_url);
}

$ttl      = (int)($config['token_ttl'] ?? 3600);

$username = trim($_GET['username'] ?? '');
$password = $_GET['password'] ?? '';
$type     = strtolower($_GET['type'] ?? 'm3u');

$link_type = strtolower($_GET['link'] ?? 'auto'); // auto|direct_protected|standard_protected

if ($username === '' || $password === '') {
  http_response_code(400);
  echo "Missing username or password";
  exit;
}

$pdo = db();

/* user */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$username]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
  http_response_code(401);
  echo "Invalid credentials";
  exit;
}

/* subscription+plan */
$st = $pdo->prepare("
  SELECT s.*, p.max_streams
  FROM subscriptions s
  JOIN plans p ON p.id=s.plan_id
  WHERE s.user_id=? AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())
  ORDER BY s.ends_at DESC LIMIT 1
");
$st->execute([$user['id']]);
$sub = $st->fetch();

if (!$sub) {
  http_response_code(403);
  echo "No active subscription";
  exit;
}

/*
  App-config support:
  The app can call:
    get.php?username=...&password=...&type=config

  Values are pulled from:
    - optional per-user fields if you later add them to DB:
        users.tmdb_api_key, users.app_logo_url, users.tmdb_region
    - otherwise from config.php:
        'tmdb_api_key', 'app_logo_url', 'tmdb_region'
*/

$user_tmdb = $user['tmdb_api_key'] ?? '';
$user_logo = $user['app_logo_url'] ?? '';
$user_region = $user['tmdb_region'] ?? '';

$tmdb_api_key = $user_tmdb ?: ($config['tmdb_api_key'] ?? '');
$app_logo_url = $user_logo ?: ($config['app_logo_url'] ?? '');
$tmdb_region  = $user_region ?: ($config['tmdb_region'] ?? 'US');

if ($type === 'config') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'tmdb_api_key' => $tmdb_api_key,
    'app_logo_url' => $app_logo_url,
    'tmdb_region'  => $tmdb_region,
    'site_url'     => $site_url,
    'token_ttl'    => $ttl
  ], JSON_UNESCAPED_SLASHES);
  exit;
}

$adult_ok = !empty($user["allow_adult"]);

/* channels */
$channels_sql = "
  SELECT id,name,group_title,tvg_id,tvg_name,tvg_logo,stream_url,direct_play,container_ext
  FROM channels
  ".($adult_ok ? "" : "WHERE IFNULL(is_adult,0)=0
  ")."ORDER BY group_title,name
";
$channels = $pdo->query($channels_sql)->fetchAll();

header("Content-Type: application/x-mpegURL; charset=utf-8");
header('Content-Disposition: attachment; filename="playlist_'.$username.'.m3u"');

echo "#EXTM3U
";

foreach ($channels as $c) {
  $group = $c['group_title'] ? ' group-title="'.e($c['group_title']).'"' : '';
  $tvgId = $c['tvg_id'] ? ' tvg-id="'.e($c['tvg_id']).'"' : '';
  $tvgNm = $c['tvg_name'] ? ' tvg-name="'.e($c['tvg_name']).'"' : '';
  $logo  = $c['tvg_logo'] ? ' tvg-logo="'.e($c['tvg_logo']).'"' : '';

  echo '#EXTINF:-1'.$tvgId.$tvgNm.$logo.$group.','.$c['name']."
";

  // Decide extension: per-channel override or detect
  $ext = $c['container_ext'];
  if (!$ext) {
    $ext = preg_match('/\.m3u8(\?|$)/i', $c['stream_url']) ? 'm3u8' : 'ts';
  }

  $exp   = time() + $ttl;
  $token = make_token($username, (int)$c['id'], $exp);

  // Link output modes:
  //  - auto: direct_play channels show real source, others show protected DIRECT link (stream/index.php)
  //  - direct_protected: always show protected DIRECT link (stream/index.php)
  //  - standard_protected: always show protected STANDARD Xtream link (/live/...)
  if ($link_type === 'direct_protected') {
    $hidden = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=".rawurlencode($password)."&id=".$c['id'];
    $hidden .= "&exp=".$exp."&token=".$token;

  } elseif ($link_type === 'standard_protected') {
    $hidden = $site_url."/live/".rawurlencode($username)."/".rawurlencode($password)."/".$c['id'].".".$ext;
    $hidden .= "?exp=".$exp."&token=".$token;

  } else { // auto
    if ((int)$c['direct_play'] === 1) {
      echo $c['stream_url']."
";
      continue;
    }
    $hidden = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=".rawurlencode($password)."&id=".$c['id'];
    $hidden .= "&exp=".$exp."&token=".$token;
  }

  echo $hidden."
";
}
