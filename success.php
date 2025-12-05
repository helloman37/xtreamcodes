<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
session_start();
$pdo=db();
$orderId=(int)($_GET['order'] ?? 0);
$st=$pdo->prepare("SELECT o.*, p.name plan_name FROM orders o JOIN plans p ON p.id=o.plan_id WHERE o.id=?");
$st->execute([$orderId]);
$o=$st->fetch();
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Success</title>
<link rel="stylesheet" href="store.css"></head><body>
<div class="wrap">
  <?php include __DIR__."/topbar.php"; ?>
<div class="card" style="max-width:560px;margin:0 auto;">
    <h3>Payment Successful</h3>
    <p class="muted">Your account is active.</p>
    <?php if($o): ?>
      <p>Order #<?=$o['id']?> — Plan: <?=e($o['plan_name'])?></p>
    <?php endif; ?>
    <a class="btn green" href="dashboard.php">Go to My Account</a>
  </div>
</div>
</body></html>
