<?php
require_once __DIR__ . '/../config.php';
require_admin();

function check_stream($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $ok = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($ok === false) {
        return 'error: ' . ($err ?: 'unknown');
    }
    if ($httpCode >= 200 && $httpCode < 400) {
        return 'online';
    }
    return 'http ' . $httpCode;
}

if (isset($_GET['check'])) {
    $id = (int)$_GET['check'];
    $stmt = $pdo->prepare('SELECT * FROM channels WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $ch = $stmt->fetch();
    if ($ch) {
        $status = check_stream($ch['stream_url']);
        $stmt2 = $pdo->prepare('UPDATE channels SET status=?, last_check=NOW() WHERE id=?');
        $stmt2->execute([$status, $id]);
        $_SESSION['flash_success'] = 'Channel #' . $id . ' checked: ' . $status;
    }
    redirect('stream_checker.php');
}

$channels = $pdo->query('SELECT * FROM channels ORDER BY id DESC LIMIT 200')->fetchAll();

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">✅</span>
    <span>Stream Checker</span>
</h2>
<p>Check individual channels. This uses a simple HTTP HEAD request via cURL.</p>

<div class="panel">
    <div class="panel-header">
        <span class="panel-header-icon">📡</span>
        <span>Channels</span>
    </div>
    <div class="panel-body">
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Stream URL</th>
                <th>Status</th>
                <th>Last Check</th>
                <th>Action</th>
            </tr>
            <?php foreach ($channels as $ch): ?>
                <tr>
                    <td><?php echo (int)$ch['id']; ?></td>
                    <td><?php echo h($ch['name']); ?></td>
                    <td><?php echo h($ch['stream_url']); ?></td>
                    <td><?php echo h($ch['status']); ?></td>
                    <td><?php echo h($ch['last_check']); ?></td>
                    <td><a href="stream_checker.php?check=<?php echo (int)$ch['id']; ?>">Check</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
