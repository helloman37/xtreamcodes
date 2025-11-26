<?php
// Handle M3U import before output to keep redirects clean
require_once __DIR__ . '/../config.php';
require_admin();

function parse_m3u($content)
{
    $lines = preg_split('/\r?\n/', $content);
    $channels = [];
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#EXTM3U') === 0) {
            continue;
        }
        if (strpos($line, '#EXTINF:') === 0) {
            $current = [
                'name'       => '',
                'logo'       => '',
                'group'      => '',
                'epg_id'     => '',
                'stream_url' => '',
            ];

            if (preg_match('/tvg-id="([^"]*)"/i', $line, $m)) {
                $current['epg_id'] = $m[1];
            }
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $m)) {
                $current['logo'] = $m[1];
            }
            if (preg_match('/group-title="([^"]*)"/i', $line, $m)) {
                $current['group'] = $m[1];
            }

            $parts = explode(',', $line, 2);
            if (isset($parts[1])) {
                $current['name'] = trim($parts[1]);
            }
        } elseif ($current && strpos($line, '#') !== 0) {
            $current['stream_url'] = $line;
            $channels[] = $current;
            $current = null;
        }
    }

    return $channels;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'upload';

    $content = '';
    if ($mode === 'upload' && !empty($_FILES['m3u_file']['tmp_name'])) {
        $content = file_get_contents($_FILES['m3u_file']['tmp_name']);
    } elseif ($mode === 'url') {
        $url = trim($_POST['m3u_url'] ?? '');
        if ($url !== '') {
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            $content = @file_get_contents($url, false, $context);
            if ($content === false) {
                $_SESSION['flash_error'] = 'Failed to download M3U from URL.';
                redirect('import_m3u.php');
            }
        }
    }

    if ($content === '') {
        $_SESSION['flash_error'] = 'No M3U content received.';
        redirect('import_m3u.php');
    }

    $channels = parse_m3u($content);
    $inserted = 0;

    foreach ($channels as $ch) {
        if ($ch['name'] === '' || $ch['stream_url'] === '') {
            continue;
        }

        $category_id = null;
        if ($ch['group'] !== '') {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name=? LIMIT 1');
            $stmt->execute([$ch['group']]);
            $cat = $stmt->fetch();
            if ($cat) {
                $category_id = (int)$cat['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (name, created_at) VALUES (?, NOW())');
                $stmt->execute([$ch['group']]);
                $category_id = (int)$pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM channels WHERE name=? AND stream_url=? LIMIT 1');
        $stmt->execute([$ch['name'], $ch['stream_url']]);
        if ($stmt->fetch()) {
            continue;
        }

        $stmt = $pdo->prepare('INSERT INTO channels (name, category_id, stream_url, logo_url, epg_id, is_active, created_at) VALUES (?,?,?,?,?,1,NOW())');
        $stmt->execute([$ch['name'], $category_id, $ch['stream_url'], $ch['logo'], $ch['epg_id']]);
        $inserted++;
    }

    $_SESSION['flash_success'] = 'Imported ' . $inserted . ' channels.';
    redirect('import_m3u.php');
}

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">📥</span>
    <span>Import M3U</span>
</h2>

<p>Import channels from an M3U file or remote URL. Basic attributes supported: <code>tvg-id</code>, <code>tvg-logo</code>, <code>group-title</code>, channel name, stream URL.</p>

<form method="post" enctype="multipart/form-data">
    <h3>Upload M3U File</h3>
    <input type="hidden" name="mode" value="upload" />
    <input type="file" name="m3u_file" accept=".m3u,.m3u8" />
    <br /><br />
    <input type="submit" value="Import from File" />
</form>

<hr />

<form method="post">
    <h3>Import from URL</h3>
    <input type="hidden" name="mode" value="url" />
    <table>
        <tr>
            <td style="width:120px;">M3U URL</td>
            <td><input type="text" name="m3u_url" placeholder="http://example.com/list.m3u" /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Import from URL" /></td>
        </tr>
    </table>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
