<?php
require_once 'db.php';
require_once 'helpers.php';
session_start();
if(empty($_SESSION['store_user'])){ header("Location: login.php"); exit; }

$pdo=db();
$userId = is_array($_SESSION['store_user']) ? (int)$_SESSION['store_user']['id'] : (int)$_SESSION['store_user'];

$uSt=$pdo->prepare("SELECT * FROM users WHERE id=?");
$uSt->execute([$userId]);
$user=$uSt->fetch();
if(!$user){ header("Location: logout.php"); exit; }

// active sub
$subSt=$pdo->prepare("SELECT s.*, p.name plan_name, p.duration_days, p.is_trial
                      FROM subscriptions s
                      JOIN plans p ON p.id=s.plan_id
                      WHERE s.user_id=? AND s.status='active'
                      ORDER BY s.ends_at DESC LIMIT 1");
$subSt->execute([$userId]);
$sub=$subSt->fetch();

// trial eligibility (one per account ever)
$trialEligible = false;
$trialPlan = $pdo->query("SELECT id,name FROM plans WHERE is_trial=1 LIMIT 1")->fetch();
if($trialPlan){
  $stUsed = $pdo->prepare("SELECT 1
                           FROM subscriptions s
                           JOIN plans p ON p.id=s.plan_id
                           WHERE s.user_id=? AND p.is_trial=1
                           LIMIT 1");
  $stUsed->execute([$userId]);
  $hasTrial = $stUsed->fetchColumn();

  $stClaim = $pdo->prepare("SELECT 1 FROM trial_claims WHERE user_id=? LIMIT 1");
  $stClaim->execute([$userId]);
  $claimed = $stClaim->fetchColumn();

  if(!$hasTrial && !$claimed){
    $trialEligible = true;
  }
}

$host = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$topbar = file_get_contents(__DIR__.'/topbar.php'); // not used, include directly below
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>My Account</title>
<link rel="stylesheet" href="store.css"></head><body>
<div class="wrap">
<?php include __DIR__."/topbar.php"; ?>

  <div class="card" style="max-width:720px;margin:0 auto;">
    <h3>Welcome, <?=e($user['username'])?></h3>

    <?php if($trialEligible): ?>
      <div class="notice" style="margin-top:10px;">
        New here? Try us free for 7 days.
        <div style="margin-top:8px;">
          <a class="btn" href="trial_start.php">Start 7‑Day Trial</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if($sub): ?>
      <p class="muted">Plan: <b><?=e($sub['plan_name'])?></b>
      — Expires: <b><?=e($sub['ends_at'])?></b></p>

      <div style="margin-top:10px;">
        <div class="badge">Playlist</div>
        <pre class="linkbox"><?=e($host."/panel/get.php?username=".$user['username']."&password=YOUR_PASSWORD&type=m3u_plus")?></pre>
      </div>
      <p class="muted" style="font-size:12px;margin-top:6px;">Replace YOUR_PASSWORD with your actual password.</p>

      <div style="margin-top:10px;">
        <div class="badge">EPG XMLTV</div>
        <pre class="linkbox"><?=e($host."/panel/xmltv.php?u=".$user['username']."&p=YOUR_PASSWORD")?></pre>
      </div>
      <p class="muted" style="font-size:12px;margin-top:6px;">Replace YOUR_PASSWORD with your actual password.</p>

      <?php if($sub['is_trial']): ?>
        <div class="notice" style="margin-top:10px;">
          You’re on a 7‑day trial. Upgrade anytime to keep access.
          <div style="margin-top:8px;">
            <a class="btn" href="plans.php">Upgrade Plan</a>
          </div>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <p>No active subscription found.</p>
      <a class="btn" href="plans.php">Buy a Plan</a>
    <?php endif; ?>
  </div>
</div>
</body></html>
