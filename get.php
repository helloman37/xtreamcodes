<?php
require_once __DIR__ . '/api_common.php';

$config   = require __DIR__ . '/config.php';
$base_url = rtrim($config['base_url'], '/');

// Derive site root URL (base_url may include /public)
$site_url = rtrim($base_url, '/');
if (preg_match('~/public$~', $site_url)) {
  $site_url = preg_replace('~/public$~', '', $site_url);
}

$ttl      = (int)($config['token_ttl'] ?? 3600);

$username = trim($_GET['username'] ?? '');
$password = (string)($_GET['password'] ?? '');
$type     = strtolower($_GET['type'] ?? 'm3u');

// auto|direct_protected|standard_protected|token_protected
$link_type = strtolower($_GET['link'] ?? 'token_protected');

if ($username === '' || $password === '') {
  http_response_code(400);
  echo "Missing username or password";
  exit;
}

$pdo = db();
ensure_categories($pdo);

/* user */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$username]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
  http_response_code(401);
  echo "Invalid credentials";
  exit;
}

// Policy: IP allow/deny
$ip = get_client_ip();
if (!ip_allowed($ip, $user['ip_allowlist'] ?? null, $user['ip_denylist'] ?? null)) {
  http_response_code(403);
  echo "IP not allowed";
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
$st->execute([(int)$user['id']]);
$sub = $st->fetch(PDO::FETCH_ASSOC);

if (!$sub) {
  http_response_code(403);
  echo "No active subscription";
  exit;
}

/* app config */
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
$pkg_ids  = user_package_ids($pdo, (int)$user['id']);
[$pkg_sql, $pkg_params] = package_filter_sql($pkg_ids, 'c');

/* channels */
$sql = "
  SELECT c.id,c.name,c.group_title,c.tvg_id,c.tvg_name,c.tvg_logo,c.stream_url,c.direct_play,c.container_ext
  FROM channels c
  WHERE 1=1
    ".($adult_ok ? "" : " AND IFNULL(c.is_adult,0)=0 ")."
    $pkg_sql
  ORDER BY c.group_title,c.name
";
$st = $pdo->prepare($sql);
$st->execute($pkg_params);
$channels = $st->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type: application/x-mpegURL; charset=utf-8");
header('Content-Disposition: attachment; filename="playlist_'.$username.'.m3u"');

echo "#EXTM3U\n";

foreach ($channels as $c) {
  $group = $c['group_title'] ? ' group-title="'.e($c['group_title']).'"' : '';
  $tvgId = $c['tvg_id'] ? ' tvg-id="'.e($c['tvg_id']).'"' : '';
  $tvgNm = $c['tvg_name'] ? ' tvg-name="'.e($c['tvg_name']).'"' : '';
  $logo  = $c['tvg_logo'] ? ' tvg-logo="'.e($c['tvg_logo']).'"' : '';

  echo '#EXTINF:-1'.$tvgId.$tvgNm.$logo.$group.','.$c['name']."\n";

  // Decide extension: per-channel override or detect
  $ext = $c['container_ext'];
  if (!$ext) $ext = preg_match('/\.m3u8(\?|$)/i', (string)$c['stream_url']) ? 'm3u8' : 'ts';

  $exp   = time() + $ttl;
  $token = make_token($username, (int)$c['id'], $exp, 'live');

  // Token-only URLs (no password leak)
  $p_empty = ''; // kept for backward compatibility

  if ($link_type === 'direct_protected') {
    // stream proxy URL (querystring)
    $hidden = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=".rawurlencode($p_empty)."&id=".$c['id'];
    $hidden .= "&exp=".$exp."&token=".$token;

  } elseif ($link_type === 'standard_protected') {
    // /live/u/p/id.ext (legacy password in path)
    $hidden = $site_url."/live/".rawurlencode($username)."/".rawurlencode($password)."/".$c['id'].".".$ext;
    $hidden .= "?exp=".$exp."&token=".$token;

  } elseif ($link_type === 'auto') {
    if ((int)$c['direct_play'] === 1) {
      echo $c['stream_url']."\n";
      continue;
    }
    $hidden = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=".rawurlencode($p_empty)."&id=".$c['id'];
    $hidden .= "&exp=".$exp."&token=".$token;

  } else { // token_protected (recommended)
    // /live/u/token/id.ext?exp=...
    $hidden = $site_url."/live/".rawurlencode($username)."/".rawurlencode($token)."/".$c['id'].".".$ext;
    $hidden .= "?exp=".$exp;
  }

  echo $hidden."\n";
}

/* ---------- VOD + SERIES (m3u_plus) ---------- */
if ($type === 'm3u_plus') {
  // Movies (VOD)
  $st = $pdo->prepare("
    SELECT m.id,m.name,m.stream_url,m.poster_url,m.container_ext,IFNULL(m.is_adult,0) AS is_adult, vc.name AS cat_name
    FROM movies m
    LEFT JOIN vod_categories vc ON vc.id=m.category_id
    WHERE 1=1
      ".($adult_ok ? "" : " AND IFNULL(m.is_adult,0)=0 ")."
    ORDER BY vc.name, m.name
  ");
  $st->execute([]);
  $movies = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($movies as $m) {
    $group = ' group-title="'.e($m['cat_name'] ?: 'VOD').'"';
    $logo  = $m['poster_url'] ? ' tvg-logo="'.e($m['poster_url']).'"' : '';
    echo '#EXTINF:-1'.$logo.$group.','.$m['name']."\n";

    $ext = $m['container_ext'];
    if (!$ext) $ext = preg_match('/\.m3u8(\?|$)/i', (string)$m['stream_url']) ? 'm3u8' : 'mp4';

    $exp = time() + $ttl;
    $tok = make_token($username, (int)$m['id'], $exp, 'movie');

    if ($link_type === 'direct_protected') {
      $url = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=&id=".$m['id']."&type=movie&exp=".$exp."&token=".$tok;
    } elseif ($link_type === 'standard_protected') {
      $url = $site_url."/movie/".rawurlencode($username)."/".rawurlencode($password)."/".$m['id'].".".$ext."?exp=".$exp."&token=".$tok;
    } else {
      $url = $site_url."/movie/".rawurlencode($username)."/".rawurlencode($tok)."/".$m['id'].".".$ext."?exp=".$exp;
    }

    echo $url."\n";
  }

  // Series episodes (flattened playlist)
  $st = $pdo->prepare("
    SELECT e.id, e.title, e.season_num, e.episode_num, e.container_ext,
           s.name AS series_name, s.cover_url, sc.name AS cat_name
    FROM series_episodes e
    JOIN series s ON s.id=e.series_id
    LEFT JOIN series_categories sc ON sc.id=s.category_id
    WHERE 1=1
      ".($adult_ok ? "" : " AND IFNULL(s.is_adult,0)=0 ")."
    ORDER BY sc.name, s.name, e.season_num, e.episode_num
  ");
  $st->execute([]);
  $eps = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($eps as $e) {
    $group = ' group-title="'.e($e['cat_name'] ?: 'Series').'"';
    $logo  = $e['cover_url'] ? ' tvg-logo="'.e($e['cover_url']).'"' : '';
    $label = $e['series_name']." - S".str_pad((string)$e['season_num'],2,'0',STR_PAD_LEFT)."E".str_pad((string)$e['episode_num'],2,'0',STR_PAD_LEFT)." - ".$e['title'];
    echo '#EXTINF:-1'.$logo.$group.','.e($label)."\n";

    $ext = $e['container_ext'] ?: 'mp4';
    $exp = time() + $ttl;
    $tok = make_token($username, (int)$e['id'], $exp, 'episode');

    if ($link_type === 'direct_protected') {
      $url = $site_url."/stream/index.php?u=".rawurlencode($username)."&p=&id=".$e['id']."&type=episode&exp=".$exp."&token=".$tok;
    } elseif ($link_type === 'standard_protected') {
      $url = $site_url."/series/".rawurlencode($username)."/".rawurlencode($password)."/".$e['id'].".".$ext."?exp=".$exp."&token=".$tok;
    } else {
      $url = $site_url."/series/".rawurlencode($username)."/".rawurlencode($tok)."/".$e['id'].".".$ext."?exp=".$exp;
    }
    echo $url."\n";
  }
}
