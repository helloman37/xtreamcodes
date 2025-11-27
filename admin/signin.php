<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';
  if (admin_login($u, $p)) {
    flash_set("Welcome back, $u", "success");
    header("Location: dashboard.php");
    exit;
  }
  flash_set("Invalid login", "error");
}

$topbar = <<<HTML
<div class="topbar">
  <div class="brand"><div class="dot"></div> IPTV CONTROL PANEL</div>
  <div class="topnav">
    <a href="signin.php">Login</a>
  </div>
</div>
<div class="container">
HTML;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<div class="auth-wrap"><div class="auth-card">
<?= $topbar ?>
<div class="card" style="max-width:420px;margin:0 auto;">
  <h2>IPTV Admin Login</h2>
  <?php flash_show(); ?>
  <form method="post">
    <label>Username</label>
    <input name="username" autocomplete="username" required>
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <div style="margin-top:12px;">
      <button type="submit" style="width:100%;">Login</button>
    </div>
  </form>
</div>
</div>
</div></div>
</body>
</html>
