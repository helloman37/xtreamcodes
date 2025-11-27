<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_FILES['m3u']['tmp_name'])) {
    flash_set("No file uploaded", "error");
    header("Location: upload_m3u.php"); exit;
  }

  $content = file_get_contents($_FILES['m3u']['tmp_name']);
  $items   = parse_m3u($content);

  // defaults chosen in the upload form
  $default_direct = isset($_POST['direct_play']) ? 1 : 0;
  $default_ext    = trim($_POST['container_ext'] ?? '') ?: null; // m3u8 | ts | null(auto)
  $default_adult  = isset($_POST['adult_content']) ? 1 : 0;

  $inserted = 0;
  $stmt = $pdo->prepare("INSERT INTO channels
    (name,group_title,tvg_id,tvg_name,tvg_logo,stream_url,direct_play,container_ext,is_adult)
    VALUES (?,?,?,?,?,?,?,?,?)");

  foreach ($items as $ch) {
    if (!$ch['stream_url']) continue;
    $stmt->execute([
      $ch['name'],
      $ch['group_title'],
      $ch['tvg_id'],
      $ch['tvg_name'],
      $ch['tvg_logo'],
      $ch['stream_url'],
      $default_direct,
      $default_ext,
      $default_adult
    ]);
    $inserted++;
  }

  flash_set("Imported $inserted channels from M3U", "success");
  header("Location: channels_manager.php"); exit;
}

$topbar = file_get_contents(__DIR__ . '/topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Import M3U</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>

<div class="card" style="max-width:760px;margin:0 auto;">
  <h2>Upload / Import M3U</h2>
  <?php flash_show(); ?>
  <p class="muted">
    Upload a <b>legal</b> M3U playlist you built.
    These options apply as <b>defaults to every imported channel</b>.
  </p>

  <form method="post" enctype="multipart/form-data">
    <label>M3U File</label>
    <input type="file" name="m3u" accept=".m3u,.m3u8" required>

    
    <label class="check-row" style="margin-top:12px;">
      <input type="checkbox" name="direct_play" value="1" <?= !empty($default_direct) ? 'checked' : '' ?>>
      Direct play for all imported channels (bypass proxy / show source)
    </label>

    <label class="check-row" style="margin-top:10px;">
      <input type="checkbox" name="adult_content" value="1" <?= !empty($default_adult) ? 'checked' : '' ?>>
      Mark ALL imported streams as <b>Adult</b> content
    </label>


    <label style="margin-top:10px;">Container Extension (default)</label>
    <select name="container_ext">
      <option value="">Auto-detect per channel</option>
      <option value="m3u8">m3u8 (HLS)</option>
      <option value="ts">ts (Xtream TS)</option>
    </select>

    <div style="margin-top:12px;">
      <button type="submit">Import</button>
    </div>
  </form>
</div>

</div><!-- container -->
</main>
</div><!-- app -->
</body>
</html>
