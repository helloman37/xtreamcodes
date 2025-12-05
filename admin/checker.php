<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_admin();

$pdo = db();
$batchSize = 50;
$timeoutPerStream = 6;

// unlock session so other pages don't hang
session_write_close();
@set_time_limit(0);
@ignore_user_abort(true);

if (isset($_POST['run'])) {
  header("Location: checker.php?run=1&offset=0"); exit;
}

$running = isset($_GET['run']);
$offset  = (int)($_GET['offset'] ?? 0);

if ($running) {
  $stmt = $pdo->prepare("SELECT id, stream_url FROM channels ORDER BY id LIMIT :l OFFSET :o");
  $stmt->bindValue(':l', $batchSize, PDO::PARAM_INT);
  $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();

  $update = $pdo->prepare("UPDATE channels SET works=?, last_status_code=?, last_checked_at=NOW() WHERE id=?");

  $count = 0;
  foreach ($rows as $ch) {
    $r = check_stream_url($ch['stream_url'], $timeoutPerStream);
    $update->execute([$r['works'] ? 1 : 0, $r['code'], $ch['id']]);
    $count++;
    usleep(150000);
  }

  if ($count < $batchSize) {
    header("Location: checker.php?done=1"); exit;
  }
  header("Location: checker.php?run=1&offset=" . ($offset + $batchSize)); exit;
}

$recent = $pdo->query("SELECT * FROM channels ORDER BY last_checked_at DESC LIMIT 50")->fetchAll();
$total  = $pdo->query("SELECT COUNT(*) AS t FROM channels")->fetch()['t'];
$done   = isset($_GET['done']);
$topbar = file_get_contents(__DIR__ . '/topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Channel Health Checker</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>

<div class="card">
  <h2>Channel Health Checker</h2>
  <?php if ($done): ?><div class="pill good">HEALTH CHECK FINISHED</div><?php endif; ?>
  <p class="muted">Total Channels: <?=$total?></p>
  <form method="post">
    <button name="run" value="1">Run Health Check</button>
  </form>
</div>

<br>

<div class="card">
  <h3>Recent Results</h3>
  <table>
    <tr><th>Name</th><th>Works</th><th>Code</th><th>Checked</th></tr>
    <?php foreach($recent as $c): ?>
    <tr>
      <td><?=e($c['name'])?></td>
      <td><?=$c['works']?'<span class="pill good">OK</span>':'<span class="pill bad">DEAD</span>'?></td>
      <td><?=e($c['last_status_code'])?></td>
      <td><?=e($c['last_checked_at'])?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

</div><!-- container -->
</main>
</div><!-- app -->
</body>
</html>
