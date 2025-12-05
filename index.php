<?php require_once __DIR__ . '/helpers.php'; ?>
<!doctype html>
<html><head>
  <meta charset="utf-8">
  <title>IPTV PLATFORM Store</title>
  <link rel="stylesheet" href="store.css">
</head><body>
<div class="wrap">
  <?php include __DIR__."/topbar.php"; ?>
<div class="hero">
    <div>
      <h1>IPTV PLATFORM — Instant Activation</h1>
      <p>Pick a plan, pay, and your line opens automatically. No waiting. Your playlist and login show up instantly in your account dashboard.</p>
      <div style="display:flex;gap:10px;max-width:420px;"><a class="btn green" href="plans.php">View Plans</a><a class="btn gray" href="trial_start.php">Start 7‑Day Trial</a></div>
    </div>
    <div class="box">
      <div class="badge">What you get</div>
      <ul>
        <li>Fast activation after payment</li>
        <li>M3U + XMLTV included</li>
        <li>Works on any IPTV app</li>
        <li>Renew anytime</li>
      </ul>
    </div>
  </div>

  <?php include __DIR__.'/plans_grid.php'; ?>
</div>
</body></html>
