<?php
require_once 'db.php';
require_once 'helpers.php';

$config   = require __DIR__ . '/config.php';
$base_url = rtrim($config['base_url'], '/');

header("Content-Type: application/json; charset=utf-8");

$username = trim($_GET['username'] ?? '');
$password = $_GET['password'] ?? '';
$action   = strtolower($_GET['action'] ?? '');

if ($username === '' || $password === '') {
  echo json_encode(["user_info"=>["auth"=>0],"error"=>"Missing credentials"]);
  exit;
}

$pdo = db();

/* ---------- AUTH ---------- */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$username]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
  echo json_encode(["user_info"=>["auth"=>0],"error"=>"Invalid credentials"]);
  exit;
}

/* active sub + plan */
$st = $pdo->prepare("
  SELECT s.*, p.max_streams, p.duration_days, p.name AS plan_name
  FROM subscriptions s
  JOIN plans p ON p.id=s.plan_id
  WHERE s.user_id=? AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())
  ORDER BY s.ends_at DESC LIMIT 1
");
$st->execute([$user['id']]);
$sub = $st->fetch();
if (!$sub) {
  echo json_encode(["user_info"=>["auth"=>0],"error"=>"No active subscription"]);
  exit;
}

$adult_ok = !empty($user["allow_adult"]);

/* ---------- BASE RESPONSE (no action) ---------- */
if ($action === '') {
  $now = time();
  $exp = strtotime($sub['ends_at']);

  echo json_encode([
    "user_info" => [
      "auth" => 1,
      "username" => $username,
      "password" => $password,
      "status" => "Active",
      "exp_date" => (string)$exp,
      "is_trial" => "0",
      "active_cons" => (string)$sub['max_streams'],
      "created_at" => (string)strtotime($user['created_at']),
      "max_connections" => (string)$sub['max_streams'],
      "allowed_output_formats" => ["m3u8","ts"]
    ],
    "server_info" => [
      "url" => parse_url($base_url, PHP_URL_HOST),
      "port" => parse_url($base_url, PHP_URL_PORT) ?: (parse_url($base_url, PHP_URL_SCHEME)==='https' ? 443 : 80),
      "https_port" => 443,
      "server_protocol" => parse_url($base_url, PHP_URL_SCHEME) ?: "http",
      "rtmp_port" => "",
      "timezone" => date_default_timezone_get(),
      "timestamp_now" => $now,
      "time_now" => date("Y-m-d H:i:s",$now)
    ]
  ]);
  exit;
}

/* ---------- LIVE CATEGORIES ---------- */
if ($action === 'get_live_categories') {
  $cats = $pdo->query("
    SELECT DISTINCT IFNULL(group_title,'Uncategorized') AS grp
    FROM channels
    ".($adult_ok ? "" : "WHERE IFNULL(is_adult,0)=0\n    ")."ORDER BY grp
  ")->fetchAll();

  $out = [];
  $i=1;
  foreach($cats as $c){
    $out[] = [
      "category_id" => (string)$i,
      "category_name" => $c['grp'],
      "parent_id" => 0
    ];
    $i++;
  }
  echo json_encode($out);
  exit;
}

/* ---------- LIVE STREAMS ---------- */
if ($action === 'get_live_streams') {
  $category_id = (int)($_GET['category_id'] ?? 0);

  // map numeric category_id to actual group_title order
  $groups = $pdo->query("
     SELECT DISTINCT IFNULL(group_title,'Uncategorized') AS grp
     FROM channels ORDER BY grp
  ")->fetchAll();
  $group_name = $groups[$category_id-1]['grp'] ?? null;

  if ($group_name) {
    $st = $pdo->prepare("
      SELECT id,name,group_title,tvg_id,tvg_name,tvg_logo,stream_url,direct_play,container_ext,created_at FROM channels
      WHERE IFNULL(group_title,'Uncategorized')=?
      ORDER BY name
    ");
    $st->execute([$group_name]);
    $channels = $st->fetchAll();
  } else {
    $channels = $pdo->query("SELECT id,name,group_title,tvg_id,tvg_name,tvg_logo,stream_url,direct_play,container_ext,created_at FROM channels ".($adult_ok ? "" : "WHERE IFNULL(is_adult,0)=0 ")."ORDER BY group_title,name")->fetchAll();
  }

  // rebuild same category order
  $cat_lookup = [];
  $i=1;
  foreach($groups as $g){ $cat_lookup[$g['grp']] = $i; $i++; }

  $out = [];
  foreach($channels as $c){
    $grp = $c['group_title'] ?: 'Uncategorized';
    $cat_id = (string)($cat_lookup[$grp] ?? 0);

    $out[] = [
      "num" => (int)$c['id'],
      "name" => $c['name'],
      "stream_type" => "live",
      "stream_id" => (int)$c['id'],
      "stream_icon" => $c['tvg_logo'] ?: "",
      "epg_channel_id" => $c['tvg_id'] ?: "",
      "added" => (string)strtotime($c['created_at']),
      "category_id" => $cat_id,
      "custom_sid" => "",
      "tv_archive" => 0,
      "direct_source" => ((int)$c["direct_play"]===1 ? $c["stream_url"] : ""),
      "container_extension" => ($c["container_ext"] ?: (preg_match("/\.m3u8(\?|$)/i",$c["stream_url"]) ? "m3u8" : "ts"))
    ];
  }

  echo json_encode($out);
  exit;
}

/* ---------- SHORT EPG (optional) ---------- */
if ($action === 'get_short_epg' || $action === 'get_simple_data_table') {
  echo json_encode(["epg_listings"=>[]]);
  exit;
}

/* ---------- VOD / SERIES NOT SUPPORTED (return empty) ---------- */
if (in_array($action, [
  'get_vod_categories','get_vod_streams','get_series_categories','get_series'
])) {
  echo json_encode([]);
  exit;
}

/* fallback */
echo json_encode(["error"=>"Unknown action"]);
