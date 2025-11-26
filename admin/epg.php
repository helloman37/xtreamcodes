<?php
require_once __DIR__ . '/../config.php';
require_admin();

// Helper to get/set config value
function get_config($key, $default = '')
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT `value` FROM config WHERE `key`=? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row) {
        return $row['value'];
    }
    return $default;
}

function set_config($key, $value)
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO config (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $epg_url = trim($_POST['epg_url'] ?? '');
    set_config('epg_url', $epg_url);
    $_SESSION['flash_success'] = 'EPG URL updated.';
    redirect('epg.php');
}

$epg_url = get_config('epg_url', '');

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">🕒</span>
    <span>EPG Settings</span>
</h2>
<p>Configure your remote XMLTV EPG source. Example:<br />
<code>http://url/xmltv.php?username=xxx&amp;password=yyy</code></p>

<form method="post">
    <table>
        <tr>
            <td style="width:150px;">EPG URL</td>
            <td><input type="text" name="epg_url" value="<?php echo h($epg_url); ?>" /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Save EPG URL" /></td>
        </tr>
    </table>
</form>

<h3>How it works (basic idea)</h3>
<p>This panel stores the EPG source URL globally and stores an <code>epg_id</code> per channel (from the M3U <code>tvg-id</code> attribute).</p>
<p>You can later build a separate script that fetches <code><?php echo h($epg_url ?: 'http://your-epg-url/xmltv.php?username=&password='); ?></code> and filters
programs by each channel's <code>epg_id</code> to display EPG in your apps or player.</p>

<?php require_once __DIR__ . '/footer.php'; ?>
