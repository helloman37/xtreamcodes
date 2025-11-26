<?php
// get.php - simple Xtream-style M3U endpoint for subscribers
// Example usage:
//   http://your-domain.com/get.php?username=USER&password=PASS&type=m3u

require_once __DIR__ . '/config.php';

// Small helper to send error and exit
function send_error(string $message, int $httpCode = 403): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

// Read query parameters
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$password = isset($_GET['password']) ? trim($_GET['password']) : '';
$type     = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'm3u';

// Only support m3u (for now)
if ($type !== 'm3u' && $type !== 'm3u8' && $type !== 'm3u_plus') {
    $type = 'm3u';
}

if ($username === '' || $password === '') {
    send_error('Missing username or password.', 400);
}

// Look up user
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || $user['password'] !== $password) {
    send_error('Invalid credentials.');
}

// Check user status
if ($user['status'] !== 'active') {
    send_error('Account is not active.');
}

// Optional: check expires_at if you use it
if (!empty($user['expires_at']) && $user['expires_at'] !== '0000-00-00 00:00:00') {
    $expiresAt = strtotime($user['expires_at']);
    if ($expiresAt !== false && $expiresAt < time()) {
        send_error('Subscription expired.');
    }
}

// Fetch active channels
$sql = "SELECT c.*, cat.name AS category_name
        FROM channels c
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.is_active = 1
        ORDER BY cat.name ASC, c.name ASC";

$channels = $pdo->query($sql)->fetchAll();

// Output M3U headers
if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
header('Content-Disposition: inline; filename="playlist_' . rawurlencode($username) . '.m3u"');

// Start playlist
echo "#EXTM3U\r\n";
echo "# Generated for user: {$username}\r\n";
echo "# Date: " . gmdate('Y-m-d H:i:s') . " UTC\r\n\r\n";

foreach ($channels as $ch) {
    $name      = $ch['name'] ?? '';
    $logo      = $ch['logo_url'] ?? '';
    $epgId     = $ch['epg_id'] ?? '';
    $group     = $ch['category_name'] ?? '';
    $streamUrl = $ch['stream_url'] ?? '';

    if ($name === '' || $streamUrl === '') {
        continue;
    }

    // Build EXTINF line
    $extinfParts = [];

    if ($epgId !== '') {
        $extinfParts[] = 'tvg-id="' . str_replace('"', '', $epgId) . '"';
    }
    if ($logo !== '') {
        $extinfParts[] = 'tvg-logo="' . str_replace('"', '', $logo) . '"';
    }
    if ($group !== '') {
        $extinfParts[] = 'group-title="' . str_replace('"', '', $group) . '"';
    }

    $extinfAttr = '';
    if (!empty($extinfParts)) {
        $extinfAttr = ' ' . implode(' ', $extinfParts);
    }

    echo '#EXTINF:-1' . $extinfAttr . ',' . $name . "\r\n";
    // For now we just output the original provider URL from the DB
    echo $streamUrl . "\r\n\r\n";
}
