<?php
// scripts/epg_import.php
// XMLTV -> epg_programs importer.
// Runs from CLI or via admin/epg_import.php wrapper.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

// -----------------------
// Output helpers
// -----------------------
function epg_out(string $msg, bool $err = false): void {
  $line = rtrim($msg) . "\n";
  if (PHP_SAPI === 'cli') {
    $stream = $err ? (defined('STDERR') ? STDERR : fopen('php://stderr', 'w'))
                   : (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
    @fwrite($stream, $line);
    return;
  }
  // Web: echo raw text. The admin wrapper escapes output for display.
  echo $line;
}

function epg_finish(int $code = 0): void {
  // If called from CLI, exit with the given code.
  // If included from a web/admin wrapper, do NOT terminate the parent script.
  if (PHP_SAPI === 'cli') {
    exit($code);
  }
  return;
}

function parse_arg_value(array $args, string $key, ?string $default = null): ?string {
  // Supports: --key=value
  $prefix = '--' . $key . '=';
  foreach ($args as $a) {
    if (strpos($a, $prefix) === 0) {
      return substr($a, strlen($prefix));
    }
  }
  return $default;
}

function parse_xmltv_time_to_utc(string $s): ?string {
  // XMLTV time usually: YYYYMMDDHHMMSS +0000
  $s = trim($s);
  if ($s === '') return null;

  // Normalize: split datetime + timezone
  if (preg_match('/^(\d{14})(?:\s*([+-]\d{4}|Z))?/', $s, $m)) {
    $dt = $m[1];
    $tz = $m[2] ?? '+0000';
    if ($tz === 'Z') $tz = '+0000';

    $fmt = 'YmdHis O';
    $obj = DateTime::createFromFormat($fmt, $dt . ' ' . $tz);
    if ($obj instanceof DateTime) {
      $obj->setTimezone(new DateTimeZone('UTC'));
      return $obj->format('Y-m-d H:i:s');
    }
  }

  // Last resort
  $ts = strtotime($s);
  if ($ts === false) return null;
  return gmdate('Y-m-d H:i:s', $ts);
}

// -----------------------
// Main
// -----------------------

// Prefer $argv set by wrapper, otherwise $_SERVER['argv'].
$args = [];
if (isset($argv) && is_array($argv)) $args = $argv;
else if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) $args = $_SERVER['argv'];

$flush = (int)(parse_arg_value($args, 'flush', '0') ?? '0');
$source_id = (int)(parse_arg_value($args, 'source_id', '0') ?? '0');
$limit = (int)(parse_arg_value($args, 'limit', '0') ?? '0'); // optional

$pdo = db();

// Ensure tables exist (install.sql in this build doesn't include epg_programs).
$pdo->exec("CREATE TABLE IF NOT EXISTS epg_programs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  channel_xmltv_id VARCHAR(255) NOT NULL,
  start_utc DATETIME NOT NULL,
  stop_utc DATETIME NOT NULL,
  title VARCHAR(255) NOT NULL,
  descr TEXT NULL,
  INDEX idx_epg_channel (channel_xmltv_id),
  INDEX idx_epg_start (start_utc),
  INDEX idx_epg_stop (stop_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Find source
if ($source_id > 0) {
  $st = $pdo->prepare("SELECT * FROM epg_sources WHERE id=? LIMIT 1");
  $st->execute([$source_id]);
  $src = $st->fetch(PDO::FETCH_ASSOC);
} else {
  $src = $pdo->query("SELECT * FROM epg_sources WHERE enabled=1 ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$src || empty($src['xmltv_url'])) {
  epg_out("No enabled EPG source found. Add one in Admin → EPG Manager.", true);
  if (PHP_SAPI === 'cli') exit(1);
  return;
}

epg_out("EPG import started");
epg_out("Source: " . ($src['name'] ?? ('#' . ($src['id'] ?? ''))) );
epg_out("URL: " . $src['xmltv_url']);
epg_out("Flush: " . ($flush ? 'YES' : 'NO'));

if ($flush) {
  try {
    $pdo->exec("TRUNCATE TABLE epg_programs");
    epg_out("epg_programs truncated");
  } catch (Throwable $e) {
    epg_out("Failed to truncate epg_programs: " . $e->getMessage(), true);
  }
}

// Download XMLTV
$ch = curl_init($src['xmltv_url']);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 120,
  CURLOPT_USERAGENT => 'IPTV-EPG-Importer/1.0'
]);
$xml = curl_exec($ch);
$curl_err = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$xml) {
  epg_out("Failed to download XMLTV (HTTP {$http}): {$curl_err}", true);
  if (PHP_SAPI === 'cli') exit(1);
  return;
}

// Handle gzipped content (some servers return gzip even without headers).
if (strncmp($xml, "\x1f\x8b", 2) === 0) {
  $decoded = @gzdecode($xml);
  if ($decoded !== false) $xml = $decoded;
}

// Parse XMLTV using XMLReader for low memory usage.
$reader = new XMLReader();
if (!$reader->XML($xml, null, LIBXML_NOERROR | LIBXML_NOWARNING)) {
  epg_out("XMLReader failed to parse XML.", true);
  if (PHP_SAPI === 'cli') exit(1);
  return;
}

$ins = $pdo->prepare("INSERT INTO epg_programs (channel_xmltv_id, start_utc, stop_utc, title, descr)
                      VALUES (?,?,?,?,?)");

$count = 0;
$skipped = 0;
$pdo->beginTransaction();

try {
  while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT) continue;
    if ($reader->name !== 'programme') continue;

    $channel = $reader->getAttribute('channel') ?? '';
    $startRaw = $reader->getAttribute('start') ?? '';
    $stopRaw  = $reader->getAttribute('stop') ?? '';
    $start = parse_xmltv_time_to_utc($startRaw);
    $stop  = parse_xmltv_time_to_utc($stopRaw);
    if ($channel === '' || !$start || !$stop) {
      $skipped++;
      // Skip invalid entries
      continue;
    }

    $title = '';
    $desc = '';

    // Read inside programme element
    $depth = $reader->depth;
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'programme' && $reader->depth === $depth) {
        break;
      }
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name === 'title') {
          $title = trim($reader->readString());
        } elseif ($reader->name === 'desc') {
          $desc = trim($reader->readString());
        }
      }
    }

    if ($title === '') $title = '(No title)';

    $ins->execute([$channel, $start, $stop, mb_substr($title, 0, 255), $desc === '' ? null : $desc]);
    $count++;

    if ($limit > 0 && $count >= $limit) {
      break;
    }

    if (($count % 1000) === 0) {
      $pdo->commit();
      $pdo->beginTransaction();
      epg_out("Imported {$count} programmes...");
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  epg_out("Import failed: " . $e->getMessage(), true);
  if (PHP_SAPI === 'cli') exit(1);
  return;
} finally {
  $reader->close();
}

epg_out("Done.");
epg_out("Imported: {$count}");
if ($skipped) epg_out("Skipped invalid: {$skipped}");

if (PHP_SAPI === 'cli') exit(0);
return;
