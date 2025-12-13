<?php
require_once __DIR__ . '/api_common.php';

header("Content-Type: application/xml; charset=utf-8");

$pdo = db();
ensure_categories($pdo);

$username = trim($_GET['username'] ?? '');
$password = (string)($_GET['password'] ?? '');

if ($username === '' || $password === '') {
  http_response_code(401);
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><tv></tv>";
  exit;
}

/* user */
$st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
$st->execute([$username]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
  http_response_code(401);
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><tv></tv>";
  exit;
}

// policy: IP allow/deny
$ip = get_client_ip();
if (!ip_allowed($ip, $user['ip_allowlist'] ?? null, $user['ip_denylist'] ?? null)) {
  http_response_code(403);
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><tv></tv>";
  exit;
}

/* sub */
$st = $pdo->prepare("
  SELECT s.*
  FROM subscriptions s
  WHERE s.user_id=? AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())
  ORDER BY s.ends_at DESC LIMIT 1
");
$st->execute([(int)$user['id']]);
$sub = $st->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
  http_response_code(403);
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><tv></tv>";
  exit;
}

$adult_ok = !empty($user['allow_adult']);
$pkg_ids  = user_package_ids($pdo, (int)$user['id']);
[$pkg_sql, $pkg_params] = package_filter_sql($pkg_ids, 'c');

/* If DB has no epg_programs, proxy upstream XMLTV if configured */
$epg_count = (int)($pdo->query("SELECT COUNT(*) c FROM epg_programs")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
$src = $pdo->query("SELECT * FROM epg_sources WHERE enabled=1 ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($epg_count === 0 && $src && !empty($src['xmltv_url'])) {
  $ch = curl_init($src['xmltv_url']);
  curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'IPTV-XMLTV-Proxy/1.1'
  ]);
  $xml = curl_exec($ch);
  curl_close($ch);
  if ($xml) {
    echo $xml;
    exit;
  }
}

/* channels allowed */
$sql = "
  SELECT c.id,c.name,c.tvg_id,c.tvg_name,c.tvg_logo
  FROM channels c
  WHERE 1=1
    ".($adult_ok ? "" : " AND IFNULL(c.is_adult,0)=0 ")."
    $pkg_sql
  ORDER BY c.name
";
$st = $pdo->prepare($sql);
$st->execute($pkg_params);
$channels = $st->fetchAll(PDO::FETCH_ASSOC);

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<tv generator-info-name=\"IPTV Panel\">\n";

$ids = [];
foreach($channels as $c){
  $id = $c['tvg_id'] ?: $c['name'];
  $ids[] = $id;
  echo "  <channel id=\"" . htmlspecialchars($id, ENT_QUOTES) . "\">\n";
  echo "    <display-name>".htmlspecialchars($c['tvg_name'] ?: $c['name'])."</display-name>\n";
  if (!empty($c['tvg_logo'])) {
    echo "    <icon src=\"" . htmlspecialchars($c['tvg_logo'], ENT_QUOTES) . "\" />\n";
  }
  echo "  </channel>\n";
}

// programs window
$in = implode(',', array_fill(0, count($ids), '?'));
if ($ids) {
  $st = $pdo->prepare("
    SELECT channel_xmltv_id, start_utc, stop_utc, title, descr
    FROM epg_programs
    WHERE channel_xmltv_id IN ($in)
      AND stop_utc > (UTC_TIMESTAMP() - INTERVAL 6 HOUR)
      AND start_utc < (UTC_TIMESTAMP() + INTERVAL 2 DAY)
    ORDER BY channel_xmltv_id, start_utc
  ");
  $st->execute($ids);
  while ($p = $st->fetch(PDO::FETCH_ASSOC)) {
    // XMLTV uses YYYYMMDDHHMMSS +0000
    $start = gmdate("YmdHis +0000", strtotime($p['start_utc'].' UTC'));
    $stop  = gmdate("YmdHis +0000", strtotime($p['stop_utc'].' UTC'));
	    echo "  <programme start=\"{$start}\" stop=\"{$stop}\" channel=\"" . htmlspecialchars($p['channel_xmltv_id'], ENT_QUOTES) . "\">\n";
    echo "    <title>".htmlspecialchars($p['title'])."</title>\n";
    if (!empty($p['descr'])) echo "    <desc>".htmlspecialchars($p['descr'])."</desc>\n";
    echo "  </programme>\n";
  }
}

echo "</tv>\n";
