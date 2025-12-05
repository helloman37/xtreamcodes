<?php
require_once 'db.php';

header("Content-Type: application/xml; charset=utf-8");

$pdo = db();
$src = $pdo->query("SELECT * FROM epg_sources WHERE enabled=1 ORDER BY created_at DESC LIMIT 1")->fetch();

if ($src && !empty($src['xmltv_url'])) {
  $url = $src['xmltv_url'];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'IPTV-XMLTV-Proxy/1.0'
  ]);
  $xml = curl_exec($ch);
  curl_close($ch);

  if ($xml) {
    echo $xml;
    exit;
  }
}

/* fallback minimal xmltv */
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<tv generator-info-name=\"IPTV Panel\">\n";

$channels = $pdo->query("SELECT tvg_id,tvg_name,name,tvg_logo FROM channels")->fetchAll();
foreach($channels as $c){
  $id = $c['tvg_id'] ?: $c['name'];
  echo "  <channel id=\"".htmlspecialchars($id)."\">\n";
  echo "    <display-name>".htmlspecialchars($c['tvg_name'] ?: $c['name'])."</display-name>\n";
  if (!empty($c['tvg_logo'])) {
    echo "    <icon src=\"".htmlspecialchars($c['tvg_logo'])."\" />\n";
  }
  echo "  </channel>\n";
}

echo "</tv>\n";
