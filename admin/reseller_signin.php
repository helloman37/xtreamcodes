<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

if (!empty($_SESSION['reseller_id'])) {
  header("Location: reseller_dashboard.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';
  if (reseller_login($u, $p)) {
    flash_set("Welcome back, $u", "success");
    header("Location: reseller_dashboard.php");
    exit;
  }
  flash_set("Invalid reseller login", "error");
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reseller Login</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<div class="auth-wrap"><div class="auth-card">
<div class="container" style="max-width:440px;">
  <h1 style="margin-top:30px;">Reseller Login</h1>
  <?php flash_show(); ?>
  <form method="post" class="card">
    <label>Username</label>
    <input name="username" autocomplete="username" required>
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <div style="margin-top:12px;">
      <button type="submit" style="width:100%;">Login</button>
    </div>
  </form>
  <p class="muted" style="margin-top:10px;"><a href="signin.php">Admin login</a></p>
</div>
</div></div>
</body>
</html>
