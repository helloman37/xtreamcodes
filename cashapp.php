<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
session_start();
$pdo=db();
$orderId=(int)($_GET['order'] ?? 0);
$st=$pdo->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$orderId]);
$order=$st->fetch();
if(!$order) die("Order not found");
$cashtag = defined('CASHAPP_CASHTAG') ? trim(CASHAPP_CASHTAG) : '';
if($cashtag && $cashtag[0] !== '$') $cashtag='$'.$cashtag;
if($cashtag && $cashtag[0] !== '$') $cashtag='$'.$cashtag;
$payUrl = $cashtag ? "https://cash.app/".rawurlencode($cashtag) : "https://cash.app/";
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=".urlencode($payUrl);
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>CashApp Payment</title>
<link rel="stylesheet" href="store.css"></head><body>
<div class="wrap">
  <?php include __DIR__."/topbar.php"; ?>
<div class="card" style="max-width:560px;margin:0 auto;text-align:center;">
    <h3>CashApp Offline Payment</h3>
    <p class="muted">Scan the QR code to pay the store. After payment, contact support with your Order ID to activate.</p>
    <?php if($cashtag): ?>
      <div class="badge">Pay the store at <?=e($cashtag)?></div>
      <div style="margin-top:10px;">
        <img src="<?=$qrUrl?>" alt="CashApp QR">
      </div>
      <p style="margin-top:10px; font-weight:800;">Order ID: #<?=$orderId?></p>
      <a class="btn" style="margin-top:10px;" href="<?=$payUrl?>" target="_blank">Open CashApp Link</a>
    <?php else: ?>
      <p>No cashtag provided.</p>
    <?php endif; ?>
  </div>
</div>
</body></html>
